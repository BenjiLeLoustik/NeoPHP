<?php

declare(strict_types=1);

namespace Neo\Core\Routing\Collector;

use Neo\Core\Profiler\Interface\CollectorInterface;
use Neo\Core\Profiler\Interface\StatusAwareCollectorInterface;
use Neo\Core\Profiler\ProfilerManager;
use Neo\Core\Routing\RouterManager;

final class RouteCollector implements CollectorInterface, StatusAwareCollectorInterface
{
    private ?int $statusCode = null;

    public function __construct(
        private readonly RouterManager $router,
        private readonly ProfilerManager $profiler,
    ) {
    }

    public function setStatusCode(?int $statusCode): void
    {
        $this->statusCode = $statusCode;
    }

    public function getName(): string
    {
        return 'route';
    }

    public function collect(): array
    {
        $name = $this->router->getCurrentRouteName();

        if ($name === null) {
            return [
                'name' => null,
                'method' => $_SERVER['REQUEST_METHOD'] ?? 'GET',
                'controller' => null,
                'action' => null,
                'path' => null,
                'requirements' => [],
            ];
        }

        $details = $this->findRouteDetails($name);

        return [
            'name' => $name,
            'method' => $details['method'] ?? ($_SERVER['REQUEST_METHOD'] ?? 'GET'),
            'controller' => $details['controller'] ?? null,
            'action' => $details['action'] ?? null,
            'path' => $details['path'] ?? null,
            'requirements' => $details['requirements'] ?? [],
        ];
    }

    public function inToolbar(): bool
    {
        return true;
    }

    public function inProfiler(): bool
    {
        return true;
    }

    public function toolbarData(): array
    {
        $data = $this->collect();

        return [
            'label' => $data['method'],
            'value' => $data['name'] !== null ? '@' . $data['name'] : 'n/a',
            'badge' => $this->statusCode !== null ? (string) $this->statusCode : null,
            'badgeStatus' => true,
        ];
    }

    public function profilerData(): array
    {
        $data = $this->collect();

        return [
            'title' => 'Route',
            'badge' => $this->statusCode !== null && $this->statusCode >= 400 ? (string) $this->statusCode : null,
            'badgeType' => 'alert',
            'blocks' => [
                [
                    'type' => 'kv',
                    'section' => null,
                    'rows' => [
                        ['label' => 'Route name', 'value' => $data['name'] ?? 'n/a'],
                        ['label' => 'HTTP method', 'value' => $data['method']],
                        ['label' => 'Status code', 'value' => $this->statusCode !== null ? (string) $this->statusCode : 'n/a'],
                        ['label' => 'Controller', 'value' => $data['controller'] ?? 'n/a'],
                        ['label' => 'Action', 'value' => $data['action'] ?? 'n/a'],
                        ['label' => 'Path pattern', 'value' => $data['path'] ?? 'n/a'],
                    ],
                ],
                [
                    'type' => 'table',
                    'section' => 'Requirements',
                    'columns' => ['Parameter', 'Regex'],
                    'rows' => array_map(
                        static fn (string $param, string $regex) => [$param, $regex],
                        array_keys($data['requirements']),
                        array_values($data['requirements'])
                    ),
                ],
            ],
        ];
    }

    private function findRouteDetails(string $name): ?array
    {
        foreach ($this->router->getRoutes()->all() as $method => $methodRoutes) {
            foreach ($methodRoutes as $path => $info) {
                if ($info['name'] === $name) {
                    return [
                        'method' => $method,
                        'controller' => $info['controller'],
                        'action' => $info['action'],
                        'path' => $path,
                        'requirements' => $info['requirements'] ?? [],
                    ];
                }
            }
        }

        return null;
    }
}