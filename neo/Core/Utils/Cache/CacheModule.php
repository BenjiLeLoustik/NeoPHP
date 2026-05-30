<?php
declare(strict_types=1);

namespace Neo\Core\Utils\Cache;

use Neo\Core\DI\Container;
use Neo\Core\Module\AbstractModule;
use Neo\Core\Utils\Config\ConfigModule;

class CacheModule extends AbstractModule
{
    public function dependencies(): array
    {
        return [
            ConfigModule::class,
        ];
    }

    public function register(Container $container): void
    {
        $container->set(Cache::class, fn(Container $c) => new Cache($c));
    }

    protected function resolveDependencies(): void
    {
        $this->get(Cache::class);
    }
}