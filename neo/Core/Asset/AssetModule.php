<?php

namespace Neo\Core\Asset;

use Neo\Core\DI\Container;
use Neo\Core\Module\Abstract\AbstractModule;
use Neo\Core\Utils\Config\ConfigModule;
use Neo\Core\View\ViewModule;

class AssetModule extends AbstractModule
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

        $container->set(AssetViewExtension::class, fn(Container $c) =>
            new AssetViewExtension($c->get(AssetManager::class))
        );

        $container->tag(AssetViewExtension::class, 'twig.extension');
    }

    protected function resolveDependencies(): void
    {
        $this->get(AssetManager::class);
    }
}