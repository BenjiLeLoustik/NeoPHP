<?php
declare(strict_types=1);

namespace Neo\Core\Http\Response;

use Neo\Core\DI\Container;
use Neo\Core\DI\Exception\ContainerException;
use Neo\Core\Http\Response\Types\Response;
use Neo\Core\Module\Interface\ModuleInterface;
use ReflectionException;

class ResponseModule implements ModuleInterface
{
    public function dependencies(): array
    {
        return [];
    }

    public function register(Container $container): void
    {
        $container->set(Response::class, fn() => new Response());
        $container->set(ResponseManager::class, fn(Container $c) => new ResponseManager($c));
    }

    /**
     * @throws ContainerException
     * @throws ReflectionException
     */
    public function init(Container $container): object
    {
        $container->get(Response::class);

        return $container->get(ResponseManager::class);
    }
}