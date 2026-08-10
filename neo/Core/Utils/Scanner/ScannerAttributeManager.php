<?php
declare(strict_types=1);

namespace Neo\Core\Utils\Scanner;

use Neo\Core\Utils\Scanner\Result\AttributeScanResult;
use ReflectionAttribute;
use ReflectionClass;
use ReflectionException;
use ReflectionMethod;
use ReflectionParameter;
use ReflectionProperty;

final class ScannerAttributeManager
{
    private bool $scanClass = false;
    private bool $scanMethods = false;
    private bool $scanProperties = false;
    private bool $scanParameters = false;

    /** @var string|null $attributeFilter */
    private ?string $attributeFilter = null;

    private int $methodFlags = ReflectionMethod::IS_PUBLIC |
    ReflectionMethod::IS_PROTECTED |
    ReflectionMethod::IS_PRIVATE;

    private int $propertyFlags = ReflectionProperty::IS_PUBLIC |
    ReflectionProperty::IS_PROTECTED |
    ReflectionProperty::IS_PRIVATE;

    /** @var ReflectionClass<object> */
    private ReflectionClass $reflection;

    /**
     * @throws ReflectionException
     */
    public function __construct(string $className)
    {
        $this->reflection = new ReflectionClass($className);
    }

    public function onClass(): ScannerAttributeManager
    {
        $this->scanClass = true;
        return $this;
    }

    public function onMethods(
        int $flags = ReflectionMethod::IS_PUBLIC | ReflectionMethod::IS_PROTECTED | ReflectionMethod::IS_PRIVATE
    ): static {
        $this->scanMethods = true;
        $this->methodFlags = $flags;
        return $this;
    }

    public function onProperties(
        int $flags = ReflectionProperty::IS_PUBLIC | ReflectionProperty::IS_PROTECTED | ReflectionProperty::IS_PRIVATE
    ): static {
        $this->scanProperties = true;
        $this->propertyFlags = $flags;
        return $this;
    }

    public function onParameters(): ScannerAttributeManager
    {
        $this->scanParameters = true;
        return $this;
    }

    public function onAll(): ScannerAttributeManager
    {
        return $this->onClass()
            ->onMethods()
            ->onProperties()
            ->onParameters();
    }

    public function withAttribute(string $attributeClass): ScannerAttributeManager
    {
        $this->attributeFilter = $attributeClass;
        return $this;
    }

    public function withAllAttributes(): ScannerAttributeManager
    {
        $this->attributeFilter = null;
        return $this;
    }

    /**
     * @return list<AttributeScanResult>
     */
    public function scan(): array
    {
        $results = [];

        if ($this->scanClass) {
            $results = array_merge($results, $this->scanTarget(
                $this->reflection,
                'class',
                $this->reflection->getShortName()
            ));
        }

        if ($this->scanMethods) {
            foreach ($this->reflection->getMethods($this->methodFlags) as $method) {
                $target = $this->reflection->getShortName() . '::' . $method->getName() . '()';
                $results = array_merge($results, $this->scanTarget($method, 'method', $target));

                if ($this->scanParameters) {
                    foreach ($method->getParameters() as $param) {
                        $paramTarget = $this->reflection->getShortName()
                            . '::' . $method->getName()
                            . '($' . $param->getName() . ')';
                        $results = array_merge($results, $this->scanTarget($param, 'parameter', $paramTarget));
                    }
                }
            }
        }

        if ($this->scanProperties) {
            foreach ($this->reflection->getProperties($this->propertyFlags) as $property) {
                $target = $this->reflection->getShortName() . '::$' . $property->getName();
                $results = array_merge($results, $this->scanTarget($property, 'property', $target));
            }
        }

        return $results;
    }

    /**
     * @param ReflectionClass<object>|ReflectionMethod|ReflectionProperty|ReflectionParameter $reflector
     * @param 'class'|'method'|'property'|'parameter' $type
     * @param string $targetLabel
     * @return list<AttributeScanResult>
     */
    private function scanTarget(
        ReflectionClass|ReflectionMethod|ReflectionProperty|ReflectionParameter $reflector,
        string $type,
        string $targetLabel
    ): array {
        $entries = [];

        $rawAttributes = $this->attributeFilter !== null
            ? $reflector->getAttributes($this->attributeFilter, ReflectionAttribute::IS_INSTANCEOF)
            : $reflector->getAttributes();

        foreach ($rawAttributes as $rawAttr) {
            $entries[] = new AttributeScanResult(
                target: $targetLabel,
                attribute: $rawAttr->newInstance(),
                arguments: $rawAttr->getArguments(),
                type: $type,
                reflection: $reflector,
            );
        }

        return $entries;
    }

}