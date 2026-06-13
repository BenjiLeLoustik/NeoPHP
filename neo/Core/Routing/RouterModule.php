<?php
declare(strict_types=1);

namespace Neo\Core\Routing;

use Neo\Core\DI\Container;
use Neo\Core\DI\Exception\ContainerException;
use Neo\Core\Module\AbstractModule;
use Neo\Core\Security\SecurityModule;
use Neo\Core\View\ViewModule;

class RouterModule extends AbstractModule
{
    public function dependencies(): array
    {
        return [
            ViewModule::class,
            SecurityModule::class,
        ];
    }

    public function register(Container $container): void
    {
        $container->set(Router::class, fn(Container $c) => new Router($c));

        $container->set(RouterViewExtension::class, fn(Container $c) =>
            new RouterViewExtension($c->get(Router::class))
        );

        $container->tag(RouterViewExtension::class, 'twig.extension');
    }

    /**
     * @throws ContainerException
     */
    protected function resolveDependencies(): void
    {
        $this->get(Router::class);
    }
}