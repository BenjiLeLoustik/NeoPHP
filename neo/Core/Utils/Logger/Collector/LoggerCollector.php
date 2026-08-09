<?php

declare(strict_types=1);

namespace Neo\Core\Utils\Logger\Collector;

use Neo\Core\Profiler\Interface\CollectorInterface;
use Neo\Core\Tools\Debug\Dumper;
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
        return false;
    }

    public function inProfiler(): bool
    {
        return true;
    }

    public function toolbarData(): array
    {
        return [
            'label' => 'Logger',
            'value' => '',
            'badge' => null,
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
                        'type' => 'log-list',
                        'section' => null,
                        'rows' => array_map(
                            fn (array $r) => [
                                'time' => date('H:i:s', (int) $r['time']) . '.' . str_pad((string) round(($r['time'] - (int) $r['time']) * 1000), 3, '0', STR_PAD_LEFT),
                                'channel' => $r['channel'],
                                'origin' => $r['origin'],
                                'message' => $r['message'],
                                'context' => $this->formatContext($r['context']),
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

    /**
     * @param array<string, mixed> $context
     */
    private function formatContext(array $context): string|array
    {
        if ($context === []) {
            return '';
        }

        if (count($context) === 1 && isset($context['trace']) && is_string($context['trace'])) {
            return $context['trace'];
        }

        $hasComplexValue = false;
        foreach ($context as $value) {
            if (!is_scalar($value) && $value !== null) {
                $hasComplexValue = true;
                break;
            }
        }

        if ($hasComplexValue) {
            return [
                'raw' => true,
                'html' => new Dumper()->render([$context]),
            ];
        }

        $lines = [];

        foreach ($context as $key => $value) {
            $formatted = match (true) {
                is_string($value) => $value,
                is_scalar($value) || $value === null => var_export($value, true),
                default => json_encode($value, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            };

            $lines[] = $key . ': ' . $formatted;
        }

        return implode("\n", $lines);
    }
}