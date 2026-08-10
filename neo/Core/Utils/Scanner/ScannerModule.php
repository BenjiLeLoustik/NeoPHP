<?php

declare(strict_types=1);

namespace Neo\Core\Utils\Scanner;

use Neo\Core\DI\Container;
use Neo\Core\Module\Interface\ModuleInterface;

class ScannerModule implements ModuleInterface
{
    public function dependencies(): array
    {
        return [];
    }

    public function register(Container $container): void
    {
        $container->set(ScannerAttributeManager::class, fn (Container $container) => function (string $className) {
            return new ScannerAttributeManager($className);
        });
    }

    public function init(Container $container): object
    {
        return $this;
    }
}