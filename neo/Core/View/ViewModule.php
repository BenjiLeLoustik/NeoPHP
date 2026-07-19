<?php
declare(strict_types=1);

namespace Neo\Core\View;

use Neo\Core\DI\Container;
use Neo\Core\Extension\ExtensionManager;
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

    protected function resolveDependencies(): void
    {
        $view = $this->get(ViewManager::class);
        $extensions = $this->get(ExtensionManager::class)->getViewExtensions();

        foreach ($extensions as $extension) {
            $view->addExtension($extension);
        }
    }
}