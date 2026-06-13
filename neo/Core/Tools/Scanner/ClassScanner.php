<?php
declare(strict_types=1);

namespace Neo\Core\Tools\Scanner;

final class ClassScanner extends AbstractScanner
{
    private array $parents = [];

    private function __construct() {}

    public static function scan(): static
    {
        return new static();
    }

    public function extending(string $parent): static
    {
        $this->parents[] = $parent;
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

                if ($reflection->isAbstract() || $reflection->isInterface()) {
                    continue;
                }

                foreach ($this->parents as $parent) {
                    if ($reflection->isSubclassOf($parent)) {
                        $results[] = [
                            'class' => $reflection,
                            'parent' => $parent
                        ];
                        break;
                    }
                }
            }
        }

        return $results;
    }
}