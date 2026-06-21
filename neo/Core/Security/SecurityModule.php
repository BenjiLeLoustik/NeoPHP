<?php
declare(strict_types=1);

namespace Neo\Core\Security;

use Neo\Core\DI\Container;
use Neo\Core\DI\Exception\ContainerException;
use Neo\Core\Http\Client\ClientModule;
use Neo\Core\Http\HttpModule;
use Neo\Core\Module\Abstract\AbstractModule;
use Neo\Core\Security\Auth\AuthManager;
use Neo\Core\Security\Auth\PasswordManager;
use Neo\Core\Security\Csrf\CsrfTokenManager;
use Neo\Core\Security\Middleware\MiddlewareManager;
use Neo\Core\Utils\Config\ConfigModule;
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
        $container->set(PasswordManager::class, fn() => new PasswordManager());
        $container->set(CsrfTokenManager::class, fn() => new CsrfTokenManager());
        $container->set(AuthManager::class, fn(Container $c) => new AuthManager($c));
        $container->set(MiddlewareManager::class, fn(Container $c) => new MiddlewareManager($c));

        $container->set(SecurityViewExtension::class, fn(Container $container) => new SecurityViewExtension(
            $container->get(AuthManager::class),
            $container->get(CsrfTokenManager::class),
        ));
        $container->tag(SecurityViewExtension::class, 'twig.extension');
    }

    /**
     * @throws ContainerException
     */
    protected function resolveDependencies(): void
    {
        $this->get(PasswordManager::class);

        $auth = $this->get(AuthManager::class);
        $csrf = $this->get(CsrfTokenManager::class);

        $this->get(MiddlewareManager::class);
    }
}