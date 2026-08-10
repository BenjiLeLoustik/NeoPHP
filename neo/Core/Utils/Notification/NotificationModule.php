<?php
declare(strict_types=1);

namespace Neo\Core\Utils\Notification;

use Neo\Core\DI\Container;
use Neo\Core\DI\Exception\ContainerException;
use Neo\Core\Module\Interface\ModuleInterface;
use Neo\Core\Utils\Config\ConfigModule;
use Neo\Core\View\ViewModule;

class NotificationModule implements ModuleInterface
{
    public function dependencies(): array
    {
        return [
            ConfigModule::class,
            ViewModule::class,
        ];
    }

    public function register(Container $container): void
    {
        $container->set(NotificationManager::class, fn(Container $c) => new NotificationManager($c));
    }

    /**
     * @throws ContainerException
     * @throws \ReflectionException
     */
    public function init(Container $container): object
    {
        return $container->get(NotificationManager::class);
    }
}