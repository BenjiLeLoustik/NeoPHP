<?php
declare(strict_types=1);

namespace Neo\Core\Security\Middleware;

use Neo\Core\Controller\AbstractController;
use Neo\Core\Controller\Interface\ControllerExtensionInterface;
use Neo\Core\DI\Container;

/**
 * @method \Neo\Core\Security\Middleware\MiddlewareHandler getMiddleware()
 */
class MiddlewareControllerExtension implements ControllerExtensionInterface
{
    public function extend(AbstractController $controller, Container $container): void
    {
        $controller->registerMethod('getMiddleware', fn() => $container->get(MiddlewareHandler::class));
    }
}