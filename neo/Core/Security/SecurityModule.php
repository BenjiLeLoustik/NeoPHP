<?php
declare(strict_types=1);

namespace Neo\Core\Security;

use Neo\Core\DI\Container;
use Neo\Core\Http\Client\ClientModule;
use Neo\Core\Http\HttpModule;
use Neo\Core\Module\AbstractModule;
use Neo\Core\Security\Auth\AuthManager;
use Neo\Core\Security\Csrf\CsrfTokenManager;
use Neo\Core\Security\Middleware\MiddlewareHandler;
use Neo\Core\Security\Password\PasswordManager;
use Neo\Core\Utils\Config\ConfigModule;
use Neo\Core\View\View;
use Neo\Core\View\ViewModule;

class SecurityModule extends AbstractModule
{
    public function dependencies(): array
    {
        return [
            ConfigModule::class,
            HttpModule::class,
            ClientModule::class,
            ViewModule::class,
        ];
    }

    public function register(Container $container): void
    {
        $container->set(PasswordManager::class, fn(Container $c) => new PasswordManager($c));
        $container->set(CsrfTokenManager::class, fn() => new CsrfTokenManager());
        $container->set(AuthManager::class, fn(Container $c) => new AuthManager($c));
        $container->set(MiddlewareHandler::class, fn(Container $c) => new MiddlewareHandler($c));
    }

    protected function resolveDependencies(): void
    {
        $this->get(PasswordManager::class);

        $auth = $this->get(AuthManager::class);
        $csrf = $this->get(CsrfTokenManager::class);

        $this->get(MiddlewareHandler::class);

        $view = $this->get(View::class);
        $view->registerTwigFunction('auth_check', fn() => $auth->check());
        $view->registerTwigFunction('auth_user', fn() => $auth->user());
        $view->registerTwigFunction('auth_has_role', fn(string $role) => $auth->hasRole($role));
        $view->registerTwigFunction('csrf_token', fn(string $id = 'default') => $csrf->generateToken($id)->getValue());
    }
}