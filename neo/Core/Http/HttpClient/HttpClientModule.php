<?php
declare(strict_types=1);

namespace Neo\Core\Http\HttpClient;

use Neo\Core\DI\Container;
use Neo\Core\DI\Exception\ContainerException;
use Neo\Core\Http\HttpClient\Interface\HttpClientInterface;
use Neo\Core\Module\Interface\ModuleInterface;
use ReflectionException;

final class HttpClientModule implements ModuleInterface
{
    /**
     * @return list<class-string>
     */
    public function dependencies(): array
    {
        return [];
    }

    public function register(Container $container): void
    {
        $container->set(HttpClientManager::class, fn (Container $c) => new HttpClientManager());

        $container->set(HttpClientInterface::class, fn (Container $c) => $c->get(HttpClientManager::class));
    }

    /**
     * @throws ContainerException
     * @throws ReflectionException
     */
    public function init(Container $container): object
    {
        return $container->get(HttpClientInterface::class);
    }
}