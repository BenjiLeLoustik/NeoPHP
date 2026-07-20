<?php
declare(strict_types=1);

namespace Neo\Core\Routing;

use Neo\Core\DI\Container;
use Neo\Core\DI\Exception\ContainerException;
use Neo\Core\Module\Abstract\AbstractModule;
use Neo\Core\Routing\Extension\RouterViewExtension;
use Neo\Core\Security\Middleware\MiddlewareModule;
use Neo\Core\View\ViewModule;

class RouterModule extends AbstractModule
{
    public function dependencies(): array
    {
        return [
            ViewModule::class,
            MiddlewareModule::class,
        ];
    }

    public function register(Container $container): void
    {
        $container->set(RouterManager::class, fn(Container $c) => new RouterManager($c));

        $container->set(RouterViewExtension::class, fn(Container $c) =>
        new RouterViewExtension($c->get(RouterManager::class))
        );

        $container->tag(RouterViewExtension::class, 'twig.extension');
    }

    /**
     * @throws ContainerException
     */
    protected function resolveDependencies(): void
    {
        $this->get(RouterManager::class);
    }
}