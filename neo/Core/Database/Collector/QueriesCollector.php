<?php

declare(strict_types=1);

namespace Neo\Core\Database\Collector;

use Neo\Core\Database\DatabaseManager;
use Neo\Core\Profiler\Interface\CollectorInterface;
use Neo\Core\Tools\Debug\Dumper;

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
                    'messageLabel' => 'SQL',
                    'rows' => array_map(
                        fn (array $q) => [
                            'time' => number_format($q['duration'], 2) . ' ms',
                            'channel' => $q['connection'] ?? 'default',
                            'origin' => $q['error'] !== null ? 'ERROR' : '',
                            'message' => $q['sql'],
                            'context' => $this->formatParams($q['params'], $q['error']),
                        ],
                        $data['queries']
                    ),
                ],
            ],
        ];
    }

    /**
     * @param array<string|int, mixed> $params
     * @return array{raw: true, html: string}
     */
    private function formatParams(array $params, ?string $error): array
    {
        $html = $params !== []
            ? new Dumper()->render([$params], false)
            : '<p class="empty-state">No parameters.</p>';

        if ($error !== null) {
            $html .= '<div style="color:#dc2626;font-family:var(--mono);font-size:0.8rem;margin-top:0.5rem;">Error: '
                . htmlspecialchars($error, ENT_QUOTES, 'UTF-8') . '</div>';
        }

        return ['raw' => true, 'html' => $html];
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