<?php
declare(strict_types=1);

namespace Neo\Core\Routing;

class RouteCollection
{
    private array $routes = [];

    public function add(string $method, string $path, string $name, string $controller, string $action, array $requirements = []): void
    {
        $path = '/' . trim($path, '/');
        $this->routes[$method][$path] = [
            'name' => $name,
            'controller' => $controller,
            'action' => $action,
            'requirements' => $requirements,
        ];
    }

    public function has(string $method, string $path): bool
    {
        $path = '/' . trim($path, '/');
        return isset($this->routes[$method][$path]);
    }

    public function all(): array
    {
        return $this->routes;
    }
}