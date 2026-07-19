<?php
declare(strict_types=1);

namespace Neo\Core\Routing\Collector;

use Neo\Core\Profiler\Interface\CollectorInterface;
use Neo\Core\Routing\Router;

class RouterCollector implements CollectorInterface
{
    private ?string $controller = null;

    private ?string $action = null;

    /** @var array<string, mixed> */
    private array $params = [];

    public function __construct(
        private readonly Router $router
    ) {}

    public function getName(): string
    {
        return 'router';
    }

    /**
     * @param array<string, mixed> $params
     */
    public function setMatchedRoute(string $controller, string $action, array $params): void
    {
        $this->controller = $controller;
        $this->action = $action;
        $this->params = $params;
    }

    /**
     * @return array<string, mixed>
     */
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

    /**
     * @param array<string, mixed> $data
     */
    public function renderTab(array $data): string
    {
        $route = htmlspecialchars($data['route'] ?? '—');

        return <<<HTML
<div class="n-tab" onclick="neoSwitch('router')" title="Routing">
    <span class="n-label">Router</span>
    <span class="n-value">{$route}</span>
</div>
HTML;
    }

    /**
     * @param array<string, mixed> $data
     */
    public function renderPanel(array $data): string
    {
        $route = htmlspecialchars($data['route'] ?? '—');
        $controller = htmlspecialchars($data['controller'] ?? '—');
        $action = htmlspecialchars($data['action'] ?? '—');
        $count = $data['routes_count'] ?? 0;

        $paramsRows = '';
        foreach (($data['params'] ?? []) as $k => $v) {
            $paramsRows .= '<dt>' . htmlspecialchars($k) . '</dt>'
                . '<dd>' . htmlspecialchars((string) $v) . '</dd>';
        }

        $paramsBlock = $paramsRows
            ? "<p class=\"n-section-title\">Route params</p><dl class=\"n-kv\">{$paramsRows}</dl>"
            : '';

        return <<<HTML
<dl class="n-kv">
    <dt>Route</dt><dd>{$route}</dd>
    <dt>Controller</dt><dd>{$controller}</dd>
    <dt>Action</dt><dd>{$action}</dd>
    <dt>Saved routes</dt><dd>{$count}</dd>
</dl>
{$paramsBlock}
HTML;
    }
}