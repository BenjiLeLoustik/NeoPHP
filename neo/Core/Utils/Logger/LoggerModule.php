<?php
declare(strict_types=1);

namespace Neo\Core\Utils\Logger;

use Neo\Core\DI\Container;
use Neo\Core\DI\Exception\ContainerException;
use Neo\Core\Module\Interface\ModuleInterface;
use Neo\Core\Utils\Config\ConfigModule;

class LoggerModule implements ModuleInterface
{
    public function dependencies(): array
    {
        return [
            ConfigModule::class,
        ];
    }

    public function register(Container $container): void
    {
        $container->set(LoggerManager::class, fn(Container $c) => new LoggerManager($c));
    }

    /**
     * @throws ContainerException
     * @throws \ReflectionException
     */
    public function init(Container $container): object
    {
        return $container->get(LoggerManager::class);
    }
}