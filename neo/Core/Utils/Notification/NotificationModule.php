<?php
declare(strict_types=1);

namespace Neo\Core\Utils\Notification;

use Neo\Core\DI\Container;
use Neo\Core\DI\Exception\ContainerException;
use Neo\Core\Module\AbstractModule;

class NotificationModule extends AbstractModule
{
    public function dependencies(): array
    {
        return [];
    }

    public function register(Container $container): void
    {
        $container->set(NotificationManager::class, fn(Container $c) => new NotificationManager($c));
    }

    /**
     * @throws ContainerException
     */
    protected function resolveDependencies(): void
    {
        $this->get(NotificationManager::class);
    }
}