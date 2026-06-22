<?php
declare(strict_types=1);

namespace Neo\Core\Routing\Tests;

use Neo\Core\DI\Container;
use Neo\Core\Routing\Collection\RouteCollection;
use Neo\Core\Routing\Router;
use ReflectionException;

final class TestableRouter extends Router
{
    public function __construct(Container $container)
    {
        parent::__construct($container);
    }

    public function seedRoutes(RouteCollection $routes): void
    {
        $ref = new \ReflectionProperty(Router::class, 'routes');
        $ref->setValue($this, $routes);
    }

    /**
     * @param array<string, string> $requirements
     * @throws ReflectionException
     */
    public function exposeCompilePattern(string $route, array $requirements = []): string
    {
        $method = new \ReflectionMethod(Router::class, 'compilePattern');
        return $method->invoke($this, $route, $requirements);
    }
}