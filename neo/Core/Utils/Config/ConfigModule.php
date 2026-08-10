<?php

declare(strict_types=1);

namespace Neo\Core\Utils\Config;

use Neo\Core\DI\Container;
use Neo\Core\DI\Exception\ContainerException;
use Neo\Core\Module\Interface\ModuleInterface;

class ConfigModule implements ModuleInterface
{
    public function dependencies(): array
    {
        return [];
    }

    public function register(Container $container): void
    {
        $container->set(ConfigManager::class, fn(Container $c) => new ConfigManager($c));
    }

    /**
     * @throws ContainerException
     * @throws \ReflectionException
     */
    public function init(Container $container): object
    {
        return $container->get(ConfigManager::class);
    }
}