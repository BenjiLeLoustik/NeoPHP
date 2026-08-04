<?php
declare(strict_types=1);

namespace Neo\Core\View;

use Neo\Core\DI\Container;
use Neo\Core\DI\Exception\ContainerException;
use Neo\Core\Module\Interface\ModuleInterface;
use Neo\Core\Package\PackageModule;
use Neo\Core\Utils\Config\ConfigModule;

class ViewModule implements ModuleInterface
{
    public function dependencies(): array
    {
        return [
            ConfigModule::class,
            PackageModule::class
        ];
    }

    public function register(Container $container): void
    {
        $container->set(ViewManager::class, fn(Container $c) => new ViewManager($c));
    }

    /**
     * @throws ContainerException
     */
    public function init(Container $container): object
    {
        return $container->get(ViewManager::class);
    }
}