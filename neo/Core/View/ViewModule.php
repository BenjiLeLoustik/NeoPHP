<?php
declare(strict_types=1);

namespace Neo\Core\View;

use Neo\Core\DI\Container;
use Neo\Core\DI\Exception\ContainerException;
use Neo\Core\Module\Abstract\AbstractModule;
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
        $container->set(ViewManager::class, fn(Container $c) => new ViewManager($c));
    }

    /**
     * @throws ContainerException
     */
    protected function resolveDependencies(): void
    {
        $view = $this->get(ViewManager::class);

        foreach ($this->container->tagged('twig.extension') as $extension) {
            $view->addExtension($extension);
        }
    }
}