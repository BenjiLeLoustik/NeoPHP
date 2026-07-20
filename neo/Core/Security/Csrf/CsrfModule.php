<?php

namespace Neo\Core\Security\Csrf;

use Neo\Core\DI\Container;
use Neo\Core\DI\Exception\ContainerException;
use Neo\Core\Http\Client\ClientModule;
use Neo\Core\Http\Client\Session\Session;
use Neo\Core\Http\HttpModule;
use Neo\Core\Http\Request\Request;
use Neo\Core\Module\Abstract\AbstractModule;

class CsrfModule extends AbstractModule
{
    /**
     * @return array<class-string>
     */
    public function dependencies(): array
    {
        return [
            ClientModule::class,
        ];
    }

    public function register(Container $container): void
    {
        $container->set(CsrfManager::class, fn(Container $c) => new CsrfManager(
            $c->get(Session::class),
            $c->get(Request::class),
        ));

        $container->set(CsrfTokenManager::class, fn() => new CsrfTokenManager());
    }

    /**
     * @throws ContainerException
     */
    protected function resolveDependencies(): void
    {
        $this->get(CsrfManager::class);
        $this->get(CsrfTokenManager::class);
    }
}