<?php

declare(strict_types=1);

namespace Neo\Core\Security\Middleware\Collector;

use Neo\Core\DI\Container;
use Neo\Core\Profiler\Interface\CollectorInterface;
use Neo\Core\Security\Middleware\MiddlewareManager;
use Neo\Core\Tools\Debug\Dumper;
use Neo\Core\Utils\Cache\CacheManager;

final class MiddlewareCollector implements CollectorInterface
{
    public function __construct(private readonly Container $container)
    {
    }

    public function getName(): string
    {
        return 'middleware';
    }

    public function collect(): array
    {
        /** @var MiddlewareManager $middleware */
        $middleware = $this->container->get(MiddlewareManager::class);

        $log = $middleware->getExecutionLog();
        $blockedCount = count(array_filter($log, static fn (array $m) => !$m['passed']));

        return [
            'total' => count($log),
            'blockedCount' => $blockedCount,
            'maintenanceTriggered' => $middleware->wasMaintenanceTriggered(),
            'middlewares' => $log,
        ];
    }

    public function inToolbar(): bool
    {
        return false;
    }

    public function inProfiler(): bool
    {
        return true;
    }

    public function toolbarData(): array
    {
        return [
            'label' => 'Middleware',
            'value' => '',
            'badge' => null,
        ];
    }

    public function profilerData(): array
    {
        $data = $this->collect();

        if ($data['maintenanceTriggered']) {
            return [
                'title' => 'Middleware',
                'badge' => '!',
                'badgeType' => 'alert',
                'group' => 'Security',
                'blocks' => [
                    [
                        'type' => 'kv',
                        'section' => null,
                        'rows' => [
                            ['label' => 'Status', 'value' => 'Request intercepted by #[Maintenance] before any middleware ran.'],
                        ],
                    ],
                ],
            ];
        }

        if ($data['total'] === 0) {
            return [
                'title' => 'Middleware',
                'badge' => null,
                'group' => 'Security',
                'metrics' => [
                    ['label' => 'Executed', 'value' => '0'],
                ],
                'blocks' => [
                    [
                        'type' => 'kv',
                        'section' => null,
                        'rows' => [
                            ['label' => 'Status', 'value' => 'No middleware was executed for this route.'],
                        ],
                    ],
                ],
            ];
        }

        return [
            'title' => 'Middleware',
            'badge' => $data['blockedCount'] > 0 ? (string) $data['blockedCount'] : null,
            'badgeType' => 'alert',
            'group' => 'Security',
            'metrics' => [
                ['label' => 'Executed', 'value' => (string) $data['total']],
                ['label' => 'Blocked', 'value' => (string) $data['blockedCount']],
            ],
            'blocks' => [
                [
                    'type' => 'table',
                    'section' => null,
                    'columns' => ['Middleware', 'Scope', 'Result', 'On error', 'Duration'],
                    'rows' => array_map(
                        static fn (array $m) => [
                            $m['class'],
                            $m['scope'],
                            $m['passed'] ? 'Passed' : 'Blocked',
                            $m['onError'],
                            $m['duration'] . ' ms',
                        ],
                        $data['middlewares']
                    ),
                ],
                [
                    'type' => 'log-list',
                    'section' => 'Details',
                    'rows' => array_map(
                        fn (array $m) => [
                            'time' => number_format($m['duration'], 2) . ' ms',
                            'channel' => $m['scope'],
                            'origin' => $m['passed'] ? 'PASSED' : 'BLOCKED',
                            'message' => $m['class'],
                            'context' => $this->formatContext($m),
                        ],
                        $data['middlewares']
                    ),
                ],
            ],
        ];
    }

    /**
     * @param array{class: class-string, params: array<string, mixed>, priority: int, redirect: string|null, message: string, errorClass: string|null} $m
     * @return array{raw: true, html: string}
     */
    private function formatContext(array $m): array
    {
        $meta = [];
        $meta[] = 'priority: ' . $m['priority'];

        if ($m['redirect'] !== null) {
            $meta[] = 'redirect: ' . $m['redirect'];
        }

        if ($m['message'] !== '') {
            $meta[] = 'message: ' . $m['message'];
        }

        if ($m['errorClass'] !== null) {
            $meta[] = 'exception: ' . $m['errorClass'];
        }

        $rateLimitInfo = $this->rateLimitInfo($m);
        if ($rateLimitInfo !== null) {
            $meta[] = $rateLimitInfo;
        }

        $html = $m['params'] !== []
            ? new Dumper()->render([$m['params']], false)
            : '<p class="empty-state">No params.</p>';

        $html .= '<div style="color:var(--text-faint);font-family:var(--mono);font-size:0.76rem;margin-top:0.6rem;white-space:pre-wrap;">'
            . htmlspecialchars(implode("\n", $meta), ENT_QUOTES, 'UTF-8') . '</div>';

        return ['raw' => true, 'html' => $html];
    }

    /**
     * @param array{class: class-string, params: array<string, mixed>} $m
     */
    private function rateLimitInfo(array $m): ?string
    {
        $isRateLimit = str_contains($m['class'], 'RateLimitMiddleware');

        if (!$isRateLimit) {
            return null;
        }

        $prefix = str_contains($m['class'], 'AuthRateLimitMiddleware') ? 'auth_rate_limit:' : 'rate_limit:';
        $maxAttempts = $m['params']['maxAttempts'] ?? null;

        $cacheLog = CacheManager::getLog();
        $latestSet = null;

        foreach ($cacheLog as $entry) {
            if ($entry['action'] === 'set' && str_starts_with($entry['key'], $prefix)) {
                $latestSet = $entry;
            }
        }

        if ($latestSet === null || $maxAttempts === null) {
            return null;
        }

        return sprintf('rate limit: %s / %s attempts used', $latestSet['value'], $maxAttempts);
    }
}