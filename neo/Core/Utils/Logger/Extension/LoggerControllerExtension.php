<?php
declare(strict_types=1);

namespace Neo\Core\Utils\Logger\Extension;

use Neo\Core\Controller\AbstractController;
use Neo\Core\Controller\Interface\ControllerExtensionInterface;
use Neo\Core\DI\Container;
use Neo\Core\Extension\Attribute\Extension;
use Neo\Core\Extension\Enum\ExtensionTypeEnum;
use Neo\Core\Utils\Logger\Logger;

/**
 * @method \Neo\Core\Utils\Logger\Logger getLogger()
 */
#[Extension(type: ExtensionTypeEnum::CONTROLLER)]
class LoggerControllerExtension implements ControllerExtensionInterface
{
    public function extend(AbstractController $controller, Container $container): void
    {
        $controller->registerMethod('getLogger', fn() => $container->get(Logger::class));
    }
}