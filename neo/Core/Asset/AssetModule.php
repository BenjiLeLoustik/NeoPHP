<?php

namespace Neo\Core\Asset;

use Neo\Core\DI\Container;
use Neo\Core\Module\AbstractModule;

class AssetModule extends AbstractModule
{
    public function dependencies(): array
    {
        return [
            // ConfigModule::class,
            // ViewModule::class
        ];
    }

    public function register(Container $container): void
    {
        $container->set(AssetHandler::class, fn (Container $c) => new AssetHandler($c));
    }

    protected function resolveDependencies(): void
    {
        $this->get(AssetHandler::class);
    }
}