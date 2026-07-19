<?php
declare(strict_types=1);

namespace Neo\Core\Security\Middleware\Extension;

use Neo\Core\Controller\AbstractController;
use Neo\Core\Controller\Interface\ControllerExtensionInterface;
use Neo\Core\DI\Container;
use Neo\Core\Extension\Attribute\Extension;
use Neo\Core\Extension\Enum\ExtensionTypeEnum;
use Neo\Core\Security\Middleware\MiddlewareManager;

/**
 * @method \Neo\Core\Security\Middleware\MiddlewareManager getMiddleware()
 */
#[Extension(type: ExtensionTypeEnum::CONTROLLER)]
class MiddlewareControllerExtension implements ControllerExtensionInterface
{
    public function extend(AbstractController $controller, Container $container): void
    {
        $controller->registerMethod('getMiddleware', fn() => $container->get(MiddlewareManager::class));
    }
}