<?php
declare(strict_types=1);

namespace Neo\Core\View;

use Neo\Core\DI\Container;
use Neo\Core\DI\Exception\ContainerException;
use Neo\Core\Extension\ExtensionManager;
use Neo\Core\Module\Interface\ModuleInterface;
use Neo\Core\Utils\Config\ConfigModule;

class ViewModule implements ModuleInterface
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
    public function init(Container $container): object
    {
        $manager = $container->get(ViewManager::class);

        $extensions = $container->get(ExtensionManager::class)->getViewExtensions();

        foreach ($extensions as $extension) {
            $manager->addExtension($extension);
        }

        return $manager;
    }
}