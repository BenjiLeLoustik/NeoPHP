<?php

namespace Neo\Core\Module;

use Neo\Core\DI\Container;
use Neo\Core\Module\Interface\ModuleInterface;

class AbstractModule implements Interface\ModuleInterface
{

    protected Container $container;

    public function dependencies(): array
    {
        return [];
    }

    public function register(Container $container): void
    {}

    public function boot(Container $container): void
    {
        $this->container = $container;
        $this->resolveDependencies();
    }

    protected function get(string $abstract): mixed
    {
        return $this->container->get($abstract);
    }

    protected function resolveDependencies(): void
    {}
}