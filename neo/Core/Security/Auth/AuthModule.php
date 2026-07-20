<?php

namespace Neo\Core\Security\Auth;

use Neo\Core\DI\Container;
use Neo\Core\DI\Exception\ContainerException;
use Neo\Core\Http\Client\ClientModule;
use Neo\Core\Module\Interface\ModuleInterface;
use Neo\Core\Utils\Config\ConfigModule;

class AuthModule implements ModuleInterface
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
    public function init(Container $container): object
    {
        $container->get(PasswordManager::class);

        return $container->get(AuthManager::class);
    }
}