<?php

namespace Neo\Core\Security\Middleware;

use Neo\Core\DI\Container;
use Neo\Core\DI\Exception\ContainerException;
use Neo\Core\Http\Client\ClientModule;
use Neo\Core\Http\Response\ResponseModule;
use Neo\Core\Module\Abstract\AbstractModule;
use Neo\Core\Routing\RouterModule;
use Neo\Core\View\ViewModule;

class MiddlewareModule extends AbstractModule
{
    /**
     * @return array<class-string>
     */
    public function dependencies(): array
    {
        return [
            ResponseModule::class,
            ClientModule::class,
            RouterModule::class,
            ViewModule::class,
        ];
    }

    public function register(Container $container): void
    {
        $container->set(MiddlewareManager::class, fn(Container $c) => new MiddlewareManager($c));
    }

    /**
     * @throws ContainerException
     */
    public function resolveDependencies(): void
    {
        $this->get(MiddlewareManager::class);
    }
}