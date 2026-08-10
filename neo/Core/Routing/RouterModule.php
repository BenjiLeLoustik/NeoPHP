<?php
declare(strict_types=1);

namespace Neo\Core\Routing;

use Neo\Core\DI\Container;
use Neo\Core\DI\Exception\ContainerException;
use Neo\Core\Module\Interface\ModuleInterface;
use Neo\Core\Package\PackageModule;
use Neo\Core\Utils\Config\ConfigModule;
use Neo\Core\View\ViewModule;

class RouterModule implements ModuleInterface
{
    public function dependencies(): array
    {
        return [
            ConfigModule::class,
            ViewModule::class,
            PackageModule::class
        ];
    }

    public function register(Container $container): void
    {
        $container->set(RouterManager::class, fn(Container $c) => new RouterManager($c));
    }

    /**
     * @throws ContainerException
     * @throws \ReflectionException
     */
    public function init(Container $container): object
    {
        if (PHP_SAPI === 'cli') {
            return $this;
        }

        return $container->get(RouterManager::class);
    }
}