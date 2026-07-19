<?php
declare(strict_types=1);

namespace Neo\Core\Utils\Cache;

use Neo\Core\DI\Container;
use Neo\Core\DI\Exception\ContainerException;
use Neo\Core\Module\Abstract\AbstractModule;
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
        $container->set(CacheManager::class, fn(Container $c) => new CacheManager($c));
    }

    /**
     * @throws ContainerException
     */
    protected function resolveDependencies(): void
    {
        $this->get(CacheManager::class);
    }
}