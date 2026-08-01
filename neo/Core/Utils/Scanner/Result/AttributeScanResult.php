<?php
declare(strict_types=1);

namespace Neo\Core\Utils\Scanner\Result;

use ReflectionClass;
use ReflectionMethod;
use ReflectionParameter;
use ReflectionProperty;

class AttributeScanResult
{
    /**
     * @param 'class'|'method'|'property'|'parameter' $type
     * @param array<mixed> $arguments
     * @param ReflectionClass<object>|ReflectionMethod|ReflectionProperty|ReflectionParameter $reflection
     */
    public function __construct(
        private string $target,
        private object $attribute,
        private array $arguments,
        private string $type,
        private ReflectionClass|ReflectionMethod|ReflectionProperty|ReflectionParameter $reflection,
    ) {
    }

    public function getTarget(): string
    {
        return $this->target;
    }

    public function getAttribute(): object
    {
        return $this->attribute;
    }

    /**
     * @return array<mixed>
     */
    public function getArguments(): array
    {
        return $this->arguments;
    }

    /**
     * @return 'class'|'method'|'property'|'parameter'
     */
    public function getType(): string
    {
        return $this->type;
    }

    /**
     * @return ReflectionClass<object>|ReflectionMethod|ReflectionProperty|ReflectionParameter
     */
    public function getReflection(): ReflectionClass|ReflectionMethod|ReflectionProperty|ReflectionParameter
    {
        return $this->reflection;
    }
}