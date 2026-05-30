<?php

namespace Neo\Core\Utils\Config;

use Neo\Core\DI\Container;
use Neo\Core\Module\AbstractModule;

class ConfigModule extends AbstractModule
{
    public function dependencies(): array
    {
        return [];
    }

    public function register(Container $container): void
    {
        $container->set(Config::class, fn(Container $c) => new Config($c));
    }

    protected function resolveDependencies(): void
    {
        $this->get(Config::class);
    }
}