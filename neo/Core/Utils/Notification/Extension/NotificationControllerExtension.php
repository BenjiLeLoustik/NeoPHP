<?php
declare(strict_types=1);

namespace Neo\Core\Utils\Notification\Extension;

use Neo\Core\Controller\AbstractController;
use Neo\Core\Controller\Interface\ControllerExtensionInterface;
use Neo\Core\DI\Container;
use Neo\Core\Extension\Attribute\Extension;
use Neo\Core\Extension\Enum\ExtensionTypeEnum;
use Neo\Core\Utils\Notification\NotificationManager;

/**
 * @method \Neo\Core\Utils\Notification\NotificationManager getNotification()
 */
#[Extension(type: ExtensionTypeEnum::CONTROLLER)]
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