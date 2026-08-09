<?php

declare(strict_types=1);

namespace Neo\Core\Utils\Cache\Collector;

use Neo\Core\Profiler\Interface\CollectorInterface;
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
        $hits = count(array_filter($gets, static fn (array $l) => $l['hit'] === true));
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
                    'type' => 'table',
                    'section' => null,
                    'columns' => ['Action', 'Key', 'Hit', 'Value', 'TTL', 'Duration'],
                    'rows' => array_map(
                        static fn (array $op) => [
                            $op['action'],
                            $op['key'],
                            $op['hit'] === null ? '—' : ($op['hit'] ? 'Yes' : 'No'),
                            $op['value'] ?? '—',
                            $op['ttl'] !== null ? $op['ttl'] . 's' : '—',
                            $op['duration'] . ' ms',
                        ],
                        $data['operations']
                    ),
                ],
            ],
        ];
    }
}