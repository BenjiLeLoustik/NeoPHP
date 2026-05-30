<?php
declare(strict_types=1);

namespace Neo\Core\View;

use Neo\Core\DI\Container;
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

    protected function resolveDependencies(): void
    {
        $this->get(View::class);
    }
}