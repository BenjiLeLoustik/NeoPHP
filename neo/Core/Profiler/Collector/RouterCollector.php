<?php
declare(strict_types=1);

namespace Neo\Core\Profiler\Collector;

use Neo\Core\Routing\Router;

class RouterCollector implements CollectorInterface
{
    private ?string $controller = null;
    private ?string $action = null;
    private array $params = [];

    public function __construct(private readonly Router $router) {}

    public function getName(): string
    {
        return 'router';
    }

    public function setMatchedRoute(string $controller, string $action, array $params): void
    {
        $this->controller = $controller;
        $this->action = $action;
        $this->params = $params;
    }

    public function collect(): array
    {
        return [
            'route' => $this->router->getCurrentRouteName(),
            'controller' => $this->controller,
            'action' => $this->action,
            'params' => $this->params,
            'routes_count' => count(
                array_merge(
                    ...array_values($this->router->getRoutes()->all())
                )
            ),
        ];
    }
}