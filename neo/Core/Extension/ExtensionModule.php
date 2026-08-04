<?php
declare(strict_types=1);

namespace Neo\Core\Extension;

use Neo\Core\DI\Container;
use Neo\Core\DI\Exception\ContainerException;
use Neo\Core\Module\Interface\ModuleInterface;
use Neo\Core\Package\PackageModule;

final class ExtensionModule implements ModuleInterface
{
    public function dependencies(): array
    {
        return [
            PackageModule::class
        ];
    }

    public function register(Container $container): void
    {
        $container->set(ExtensionManager::class, fn(Container $c) => new ExtensionManager($c));
    }

    /**
     * @throws ContainerException
     */
    public function init(Container $container): object
    {
        return $container->get(ExtensionManager::class);
    }
}