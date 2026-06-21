<?php

namespace Neo\Core\Utils\Scanner;

use Neo\Core\DI\Container;
use Neo\Core\DI\Exception\ContainerException;
use Neo\Core\Module\Abstract\AbstractModule;
use Neo\Core\Utils\Scanner\Attribute\ScannerAttribute;

class ScannerModule extends AbstractModule
{
    public function dependencies(): array
    {
        return [];
    }

    public function register(Container $container): void
    {
        $container->set(ScannerAttribute::class, fn (Container $container) => function (string $className) {
            return new ScannerAttribute($className);
        });
    }

    /**
     * @throws ContainerException
     */
    protected function resolveDependencies(): void
    {
        $this->get(ScannerAttribute::class);
    }
}