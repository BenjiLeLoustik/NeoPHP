<?php
declare(strict_types=1);

namespace Neo\Core\Http\Response;

use Neo\Core\DI\Container;
use Neo\Core\Http\Response\Types\Response;
use Neo\Core\Module\Abstract\AbstractModule;

class ResponseModule extends AbstractModule
{
    public function dependencies(): array
    {
        return [];
    }

    public function register(Container $container): void
    {
        $container->set(Response::class, fn() => new Response());
        $container->set(ResponseManager::class, fn() => new ResponseManager());
    }

    protected function resolveDependencies(): void
    {
        $this->get(Response::class);
        $this->get(ResponseManager::class);
    }
}