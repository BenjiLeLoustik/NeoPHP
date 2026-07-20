<?php
declare(strict_types=1);

namespace Neo\Core\Utils\Cache;

use Neo\Core\DI\Container;
use Neo\Core\DI\Exception\ContainerException;
use Neo\Core\Module\Interface\ModuleInterface;
use Neo\Core\Utils\Config\ConfigModule;

class CacheModule implements ModuleInterface
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
    public function init(Container $container): object
    {
        return $container->get(CacheManager::class);
    }
}