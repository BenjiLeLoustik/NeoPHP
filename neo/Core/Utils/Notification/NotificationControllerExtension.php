<?php
declare(strict_types=1);

namespace Neo\Core\Utils\Notification;

use Neo\Core\Controller\AbstractController;
use Neo\Core\Controller\Interface\ControllerExtensionInterface;
use Neo\Core\DI\Container;

/**
 * @method \Neo\Core\Utils\Notification\NotificationManager getNotification()
 */
class NotificationControllerExtension implements ControllerExtensionInterface
{
    public function extend(AbstractController $controller, Container $container): void
    {
        $controller->registerMethod(
            'getNotification',
            static function () use ($container): NotificationManager {
                return new NotificationManager($container);
            }
        );
    }
}