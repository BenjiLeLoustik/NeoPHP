<?php

namespace Neo\Core\Security\Csrf;

use Neo\Core\DI\Container;
use Neo\Core\DI\Exception\ContainerException;
use Neo\Core\Http\Client\ClientModule;
use Neo\Core\Http\Request\Request;
use Neo\Core\Module\Interface\ModuleInterface;

class CsrfModule implements ModuleInterface
{
    public function dependencies(): array
    {
        return [
            ClientModule::class,
        ];
    }

    public function register(Container $container): void
    {
        $container->set(CsrfManager::class, fn(Container $c) => new CsrfManager(
            $c->get('csrf.clientModule')->session(),
            $c->get(Request::class),
        ));

        $container->set(CsrfTokenManager::class, fn() => new CsrfTokenManager());
    }

    /**
     * @throws \ReflectionException
     * @throws ContainerException
     */
    public function init(Container $container): object
    {
        if (PHP_SAPI === 'cli') {
            return $this;
        }

        $container->get(CsrfTokenManager::class);

        return $container->get(CsrfManager::class);
    }
}