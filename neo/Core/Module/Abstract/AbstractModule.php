<?php

namespace Neo\Core\Module\Abstract;

use Neo\Core\DI\Container;
use Neo\Core\DI\Exception\ContainerException;
use Neo\Core\Module\Interface;

class AbstractModule implements Interface\ModuleInterface
{

    protected Container $container;

    /**
     * @return array<class-string>
     */
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

    /**
     * @throws ContainerException
     */
    protected function get(string $abstract): mixed
    {
        return $this->container->get($abstract);
    }

    protected function resolveDependencies(): void
    {}
}