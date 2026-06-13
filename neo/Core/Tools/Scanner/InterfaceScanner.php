<?php
declare(strict_types=1);

namespace Neo\Core\Tools\Scanner;

final class InterfaceScanner extends AbstractScanner
{
    private array $interfaces = [];

    private function __construct() {}

    public static function scan(): static
    {
        return new static();
    }

    public function implementing(string $interface): static
    {
        $this->interfaces[] = $interface;
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

                foreach ($this->interfaces as $interface) {
                    if ($reflection->implementsInterface($interface)) {
                        $results[] = [
                            'class' => $reflection,
                            'interface' => $interface
                        ];
                        break;
                    }
                }
            }
        }

        return $results;
    }
}