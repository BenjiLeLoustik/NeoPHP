<?php
declare(strict_types=1);

namespace Neo\Core\Console;

use Neo\Core\DI\Container;
use Neo\Core\DI\Exception\ContainerException;
use Neo\Core\Module\Interface\ModuleInterface;
use Neo\Core\Package\PackageModule;

class ConsoleModule implements ModuleInterface
{
    /** @return list<class-string> */
    public function dependencies(): array
    {
        return [
            PackageModule::class
        ];
    }

    public function register(Container $container): void
    {
        $container->set(ConsoleManager::class, fn(Container $c) => new ConsoleManager($c));
    }

    /**
     * @throws ContainerException
     */
    public function init(Container $container): object
    {
        return $container->get(ConsoleManager::class);
    }
}