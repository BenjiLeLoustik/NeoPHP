<?php
declare(strict_types=1);

namespace Neo\Core\Tools\Scanner;

final class AttributeScanner extends AbstractScanner
{
    private string $attributeClass;
    private array $targets = [];

    private function __construct(string $attributeClass)
    {
        $this->attributeClass = $attributeClass;
    }

    public static function scan(string $attributeClass): static
    {
        return new static($attributeClass);
    }

    public function onClasses(): static
    {
        $this->targets[] = 'class';
        return $this;
    }

    public function onMethods(): static
    {
        $this->targets[] = 'method';
        return $this;
    }

    public function onProperties(): static
    {
        $this->targets[] = 'property';
        return $this;
    }

    public function getResults(): array
    {
        $results = [];

        foreach ($this->directories as $dir) {
            foreach ($this->loadClasses($dir['path'], $dir['subfolder']) as $class) {
                try {
                    $reflection = new \ReflectionClass($class);
                } catch (\ReflectionException) {
                    continue;
                }

                if (in_array('class', $this->targets)) {
                    foreach ($reflection->getAttributes($this->attributeClass) as $attr) {
                        $results[] = [
                            'class' => $reflection,
                            'attribute' => $attr->newInstance()
                        ];
                    }
                }

                if (in_array('method', $this->targets)) {
                    foreach ($reflection->getMethods(\ReflectionMethod::IS_PUBLIC) as $method) {
                        foreach ($method->getAttributes($this->attributeClass) as $attr) {
                            $results[] = [
                                'class' => $reflection,
                                'method' => $method,
                                'attribute' => $attr->newInstance()
                            ];
                        }
                    }
                }

                if (in_array('property', $this->targets)) {
                    foreach ($reflection->getProperties() as $property) {
                        foreach ($property->getAttributes($this->attributeClass) as $attr) {
                            $results[] = [
                                'class' => $reflection,
                                'property' => $property,
                                'attribute' => $attr->newInstance()
                            ];
                        }
                    }
                }
            }
        }

        return $results;
    }
}