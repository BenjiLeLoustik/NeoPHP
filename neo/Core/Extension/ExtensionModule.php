<?php
declare(strict_types=1);

namespace Neo\Core\Extension;

use Neo\Core\DI\Container;
use Neo\Core\Module\Abstract\AbstractModule;

final class ExtensionModule extends AbstractModule
{
    public function dependencies(): array
    {
        return [];
    }

    public function register(Container $container): void
    {
        $container->set(ExtensionManager::class, fn(Container $c) => new ExtensionManager($c));
    }
}