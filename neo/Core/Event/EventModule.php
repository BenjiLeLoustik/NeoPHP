<?php
declare(strict_types=1);

namespace Neo\Core\Event;

use Neo\Core\DI\Container;
use Neo\Core\DI\Exception\ContainerException;
use Neo\Core\Module\Interface\ModuleInterface;
use Neo\Core\Package\PackageModule;
use Neo\Core\Utils\Config\ConfigModule;

class EventModule implements ModuleInterface
{
    /**
     * @return list<class-string>
     */
    public function dependencies(): array
    {
        return [
            ConfigModule::class,
            PackageModule::class
        ];
    }

    public function register(Container $container): void
    {
        $container->set(EventManager::class, fn(Container $c) => new EventManager($c));
    }

    /**
     * @throws ContainerException
     * @throws \ReflectionException
     */
    public function init(Container $container): object
    {
        return $container->get(EventManager::class);
    }
}