<?php
declare(strict_types=1);

namespace Neo\Core\Utils\Logger;

use Neo\Core\DI\Container;
use Neo\Core\Module\AbstractModule;
use Neo\Core\Utils\Config\ConfigModule;

class LoggerModule extends AbstractModule
{
    public function dependencies(): array
    {
        return [
            ConfigModule::class,
        ];
    }

    public function register(Container $container): void
    {
        $container->set(Logger::class, fn(Container $c) => new Logger($c));
    }

    protected function resolveDependencies(): void
    {
        $this->get(Logger::class);
    }
}