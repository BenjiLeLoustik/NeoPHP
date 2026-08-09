<?php

declare(strict_types=1);

namespace Neo\Core\Database\Collector;

use Neo\Core\Database\DatabaseManager;
use Neo\Core\Profiler\Interface\CollectorInterface;

final class QueriesCollector implements CollectorInterface
{
    public function getName(): string
    {
        return 'queries';
    }

    public function collect(): array
    {
        $queries = DatabaseManager::getQueries();

        $totalDuration = array_sum(array_map(static fn (array $q) => $q['duration'], $queries));
        $errorCount = count(array_filter($queries, static fn (array $q) => $q['error'] !== null));

        return [
            'total' => count($queries),
            'totalDuration' => round($totalDuration, 2),
            'errorCount' => $errorCount,
            'queries' => $queries,
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
            'label' => 'Queries',
            'value' => (string) $data['total'] . ' (' . $data['totalDuration'] . ' ms)',
            'badge' => $data['errorCount'] > 0 ? (string) $data['errorCount'] : null,
            'badgeType' => 'alert',
        ];
    }

    public function profilerData(): array
    {
        $data = $this->collect();

        if ($data['total'] === 0) {
            return [
                'title' => 'Queries',
                'badge' => null,
                'group' => 'Database',
                'metrics' => [
                    ['label' => 'Total queries', 'value' => '0'],
                ],
                'blocks' => [
                    [
                        'type' => 'kv',
                        'section' => null,
                        'rows' => [
                            ['label' => 'Status', 'value' => 'No query was executed during this request.'],
                        ],
                    ],
                ],
            ];
        }

        return [
            'title' => 'Queries',
            'group' => 'Database',
            'badge' => $data['errorCount'] > 0 ? (string) $data['errorCount'] : null,
            'badgeType' => 'alert',
            'metrics' => [
                ['label' => 'Total queries', 'value' => (string) $data['total']],
                ['label' => 'Total time', 'value' => (string) $data['totalDuration'], 'unit' => 'ms'],
                ['label' => 'Errors', 'value' => (string) $data['errorCount']],
            ],
            'blocks' => [
                [
                    'type' => 'log-list',
                    'section' => null,
                    'rows' => array_map(
                        fn (array $q) => [
                            'time' => number_format($q['duration'], 2) . ' ms',
                            'channel' => $q['connection'] ?? 'default',
                            'origin' => $q['error'] !== null ? 'ERROR' : '',
                            'message' => $q['sql'],
                            'context' => $this->formatParams($q['params']) . ($q['error'] !== null ? "\n\nError: " . $q['error'] : ''),
                        ],
                        $data['queries']
                    ),
                ],
            ],
        ];
    }

    /**
     * @param array<string|int, mixed> $params
     */
    private function formatParams(array $params): string
    {
        if ($params === []) {
            return '';
        }

        $lines = [];

        foreach ($params as $key => $value) {
            $lines[] = $key . ': ' . $this->stringify($value);
        }

        return implode("\n", $lines);
    }

    private function stringify(mixed $value): string
    {
        return match (true) {
            $value === null => 'null',
            is_bool($value) => $value ? 'true' : 'false',
            is_scalar($value) => (string) $value,
            default => json_encode($value, JSON_UNESCAPED_UNICODE) ?: '',
        };
    }
}