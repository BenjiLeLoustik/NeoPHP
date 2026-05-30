<?php
declare(strict_types=1);

namespace Neo\Core\Utils\Logger;

use Neo\Core\Controller\AbstractController;
use Neo\Core\Controller\Interface\ControllerExtensionInterface;
use Neo\Core\DI\Container;

/**
 * @method \Neo\Core\Utils\Logger\Logger getLogger()
 */
class LoggerControllerExtension implements ControllerExtensionInterface
{
    public function extend(AbstractController $controller, Container $container): void
    {
        $controller->registerMethod('getLogger', fn() => $container->get(Logger::class));
    }
}