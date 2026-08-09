<?php

declare(strict_types=1);

namespace Neo\Core\Utils\Logger\Collector;

use Neo\Core\Profiler\Interface\CollectorInterface;
use Neo\Core\Utils\Logger\LoggerManager;

final class LoggerCollector implements CollectorInterface
{
    private const array ERROR_LEVELS = ['ERROR', 'CRITICAL', 'ALERT', 'EMERGENCY'];

    public function __construct(private readonly LoggerManager $logger)
    {
    }

    public function getName(): string
    {
        return 'logger';
    }

    public function collect(): array
    {
        $records = $this->logger->getRecords();
        $byLevel = [];

        foreach ($records as $record) {
            $byLevel[$record['level']][] = $record;
        }

        return [
            'total' => count($records),
            'byLevel' => $byLevel,
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
            'label' => 'Logs',
            'value' => (string) $data['total'],
            'badge' => $this->errorBadge($data['byLevel']),
        ];
    }

    public function profilerData(): array
    {
        $data = $this->collect();

        $errorCount = (int) ($this->errorBadge($data['byLevel']) ?? '0');

        if ($data['total'] === 0) {
            return [
                'title' => 'Logger',
                'badge' => null,
                'metrics' => [
                    ['label' => 'Total logs', 'value' => '0'],
                ],
                'blocks' => [
                    [
                        'type' => 'kv',
                        'section' => null,
                        'rows' => [
                            ['label' => 'Status', 'value' => 'No log written during this request.'],
                        ],
                    ],
                ],
            ];
        }

        $tabs = [];

        foreach ($data['byLevel'] as $level => $records) {
            $tabs[] = [
                'label' => $level,
                'badge' => (string) count($records),
                'badgeType' => in_array($level, self::ERROR_LEVELS, true) ? 'alert' : 'neutral',
                'blocks' => [
                    [
                        'type' => 'table',
                        'section' => null,
                        'columns' => ['Time', 'Channel', 'Origin', 'Message', 'Context'],
                        'rows' => array_map(
                            static fn (array $r) => [
                                date('H:i:s', (int) $r['time']) . '.' . str_pad((string) round(($r['time'] - (int) $r['time']) * 1000), 3, '0', STR_PAD_LEFT),
                                $r['channel'],
                                $r['origin'],
                                $r['message'],
                                $r['context'] !== [] ? json_encode($r['context'], JSON_UNESCAPED_UNICODE) : '',
                            ],
                            $records
                        ),
                    ],
                ],
            ];
        }

        return [
            'title' => 'Logger',
            'badge' => $errorCount > 0 ? (string) $errorCount : null,
            'badgeType' => 'alert',
            'metrics' => [
                ['label' => 'Total logs', 'value' => (string) $data['total']],
                ['label' => 'Errors', 'value' => (string) $errorCount],
            ],
            'blocks' => [
                ['type' => 'tabs', 'section' => null, 'tabs' => $tabs],
            ],
        ];
    }

    /**
     * @param array<string, array<int, array<string, mixed>>> $byLevel
     */
    private function errorBadge(array $byLevel): ?string
    {
        $count = 0;

        foreach (self::ERROR_LEVELS as $level) {
            $count += count($byLevel[$level] ?? []);
        }

        return $count > 0 ? (string) $count : null;
    }
}