<?php
declare(strict_types=1);

namespace Neo\Core\View;

use Neo\Core\DI\Container;
use Neo\Core\DI\Exception\ContainerException;
use Neo\Core\Module\AbstractModule;
use Neo\Core\Utils\Config\ConfigModule;

class ViewModule extends AbstractModule
{
    public function dependencies(): array
    {
        return [
            ConfigModule::class,
        ];
    }

    public function register(Container $container): void
    {
        $container->set(View::class, fn(Container $c) => new View($c));
    }

    /**
     * @throws ContainerException
     */
    protected function resolveDependencies(): void
    {
        $view = $this->get(View::class);

        foreach ($this->container->tagged('twig.extension') as $extension) {
            $view->addExtension($extension);
        }
    }
}