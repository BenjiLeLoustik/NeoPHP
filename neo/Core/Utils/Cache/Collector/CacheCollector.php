<?php

declare(strict_types=1);

namespace Neo\Core\Utils\Cache\Collector;

use Neo\Core\Profiler\Interface\CollectorInterface;
use Neo\Core\Tools\Debug\Dumper;
use Neo\Core\Utils\Cache\CacheManager;

final class CacheCollector implements CollectorInterface
{
    public function getName(): string
    {
        return 'cache';
    }

    public function collect(): array
    {
        $log = CacheManager::getLog();

        $gets = array_filter($log, static fn (array $l) => $l['action'] === 'get' || $l['action'] === 'has');
        $hits = $gets
                |> (fn (array $g): array => array_filter($g, static fn (array $l) => $l['hit'] === true))
                |> count(...);

        $misses = count($gets) - $hits;

        return [
            'total' => count($log),
            'hits' => $hits,
            'misses' => $misses,
            'operations' => $log,
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
            'label' => 'Cache',
            'value' => '',
            'badge' => null,
        ];
    }

    public function profilerData(): array
    {
        $data = $this->collect();

        if ($data['total'] === 0) {
            return [
                'title' => 'Cache',
                'badge' => null,
                'metrics' => [
                    ['label' => 'Operations', 'value' => '0'],
                ],
                'blocks' => [
                    [
                        'type' => 'kv',
                        'section' => null,
                        'rows' => [
                            ['label' => 'Status', 'value' => 'No cache operation during this request.'],
                        ],
                    ],
                ],
            ];
        }

        return [
            'title' => 'Cache',
            'badge' => null,
            'metrics' => [
                ['label' => 'Operations', 'value' => (string) $data['total']],
                ['label' => 'Hits', 'value' => (string) $data['hits']],
                ['label' => 'Misses', 'value' => (string) $data['misses']],
            ],
            'blocks' => [
                [
                    'type' => 'log-list',
                    'section' => null,
                    'timeLabel' => 'Duration',
                    'messageLabel' => 'Key',
                    'rows' => array_map(
                        fn (array $op) => [
                            'time' => $op['duration'] . ' ms',
                            'channel' => $op['action'],
                            'origin' => $op['hit'] === null ? '' : ($op['hit'] ? 'HIT' : 'MISS'),
                            'message' => $op['key'] . ($op['ttl'] !== null ? ' (TTL: ' . $op['ttl'] . 's)' : ''),
                            'context' => $this->renderContext($op['value']),
                        ],
                        $data['operations']
                    ),
                ],
            ],
        ];
    }

    /**
     * @return string|array{raw: true, html: string}
     */
    private function renderContext(?string $value): string|array
    {
        if ($value === null) {
            return '';
        }

        $decoded = json_decode($value, true);

        if (json_last_error() !== JSON_ERROR_NONE || !is_array($decoded)) {
            return $value;
        }

        return [
            'raw' => true,
            'html' => new Dumper()->render([$decoded], false),
        ];
    }
}