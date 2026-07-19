<?php
declare(strict_types=1);

namespace Neo\Core\Utils\Logger\Extension;

use Neo\Core\Controller\AbstractController;
use Neo\Core\Controller\Interface\ControllerExtensionInterface;
use Neo\Core\DI\Container;
use Neo\Core\Extension\Attribute\Extension;
use Neo\Core\Extension\Enum\ExtensionTypeEnum;
use Neo\Core\Utils\Logger\LoggerManager;

/**
 * @method \Neo\Core\Utils\Logger\LoggerManager getLogger()
 */
#[Extension(type: ExtensionTypeEnum::CONTROLLER)]
class LoggerControllerExtension implements ControllerExtensionInterface
{
    public function extend(AbstractController $controller, Container $container): void
    {
        $controller->registerMethod('getLogger', fn() => $container->get(LoggerManager::class));
    }
}