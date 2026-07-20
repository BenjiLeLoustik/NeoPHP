<?php

namespace Neo\Core\Security\Auth;

use Neo\Core\DI\Container;
use Neo\Core\DI\Exception\ContainerException;
use Neo\Core\Http\Client\ClientModule;
use Neo\Core\Http\HttpModule;
use Neo\Core\Module\Abstract\AbstractModule;
use Neo\Core\Utils\Config\ConfigModule;

class AuthModule extends AbstractModule
{
    /**
     * @return array<class-string>
     */
    public function dependencies(): array
    {
        return [
            ConfigModule::class,
            ClientModule::class
        ];
    }

    public function register(Container $container): void
    {
        $container->set(PasswordManager::class, fn() => new PasswordManager());
        $container->set(AuthManager::class, fn(Container $c) => new AuthManager($c));
    }

    /**
     * @throws ContainerException
     */
    public function resolveDependencies(): void
    {
        $this->get(PasswordManager::class);
        $this->get(AuthManager::class);
    }
}