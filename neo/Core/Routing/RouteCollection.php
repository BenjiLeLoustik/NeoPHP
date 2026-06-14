<?php
declare(strict_types=1);

namespace Neo\Core\Routing;

class RouteCollection
{
    /** @var array<string, array<string, array{name: string, controller: string, action: string, requirements: array<string, string>}>> */
    private array $routes = [];

    /**
     * @param array<string, string> $requirements
     */
    public function add(
        string $method,
        string $path,
        string $name,
        string $controller,
        string $action,
        array $requirements = []
    ): void {
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

    /**
     * @return array<string, array<string, array{name: string, controller: string, action: string, requirements: array<string, string>}>>
     */
    public function all(): array
    {
        return $this->routes;
    }
}