<?php
declare(strict_types=1);

namespace Neo\Core\Console;

use Neo\Core\DI\Container;
use Neo\Core\Module\AbstractModule;

class ConsoleModule extends AbstractModule
{
    /** @return list<class-string> */
    public function dependencies(): array
    {
        return [];
    }

    public function register(Container $container): void
    {
        $container->set(ConsoleManager::class, fn(Container $c) => new ConsoleManager($c));
    }
}