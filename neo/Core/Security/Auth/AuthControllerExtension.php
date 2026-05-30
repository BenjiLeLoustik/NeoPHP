<?php
declare(strict_types=1);

namespace Neo\Core\Security\Auth;

use Neo\Core\Controller\AbstractController;
use Neo\Core\Controller\Interface\ControllerExtensionInterface;
use Neo\Core\DI\Container;

/**
 * @method \Neo\Core\Security\Auth\AuthManager auth()
 * @method \Neo\Core\Security\Auth\PasswordManager getPasswordManager()
 */
class AuthControllerExtension implements ControllerExtensionInterface
{
    public function extend(AbstractController $controller, Container $container): void
    {
        $controller->registerMethod('auth', fn() => $container->get(AuthManager::class));
        $controller->registerMethod('getPasswordManager', fn() => $container->get(PasswordManager::class));
    }
}