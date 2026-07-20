<?php

namespace Neo\Core\Asset;

use Neo\Core\DI\Container;
use Neo\Core\DI\Exception\ContainerException;
use Neo\Core\Module\Interface\ModuleInterface;
use Neo\Core\Utils\Config\ConfigModule;
use Neo\Core\View\ViewModule;

class AssetModule implements ModuleInterface
{
    /**
     * @return list<class-string>
     */
    public function dependencies(): array
    {
        return [
            ConfigModule::class,
            ViewModule::class
        ];
    }

    public function register(Container $container): void
    {
        $container->set(AssetManager::class, fn (Container $c) => new AssetManager($c));
    }

    /**
     * @throws ContainerException
     */
    public function init(Container $container): object
    {
        return $container->get(AssetManager::class);
    }
}