<?php
declare(strict_types=1);

namespace Neo\Core\Utils\Notification;

use Neo\Core\DI\Container;
use Neo\Core\Module\AbstractModule;
use Neo\Core\Utils\Config\Config;

/**
 * Module Notification.
 */
class NotificationModule extends AbstractModule
{
    public function dependencies(): array
    {
        return [];
    }

    public function register(Container $container): void
    {
        $container->singleton(NotificationManager::class, static function (Container $c): NotificationManager {
            $config = $c->get(Config::class);
            $notificationManager = new NotificationManager($c);

            return $notificationManager;
        });
    }

    public function boot(Container $container): void
    {
        parent::boot($container);
    }
}