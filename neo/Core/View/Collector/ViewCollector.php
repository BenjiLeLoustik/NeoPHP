<?php

declare(strict_types=1);

namespace Neo\Core\View\Collector;

use Neo\Core\Profiler\Interface\CollectorInterface;
use Neo\Core\View\ViewManager;

final class ViewCollector implements CollectorInterface
{
    public function getName(): string
    {
        return 'views';
    }

    public function collect(): array
    {
        $renders = ViewManager::getRenders();

        $totalDuration = array_sum(array_map(static fn (array $r) => $r['duration'], $renders));
        $errorCount = count(array_filter($renders, static fn (array $r) => $r['error'] !== null));

        return [
            'total' => count($renders),
            'totalDuration' => round($totalDuration, 2),
            'errorCount' => $errorCount,
            'renders' => $renders,
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
            'label' => 'Views',
            'value' => '',
            'badge' => null,
        ];
    }

    public function profilerData(): array
    {
        $data = $this->collect();

        if ($data['total'] === 0) {
            return [
                'title' => 'Views',
                'badge' => null,
                'metrics' => [
                    ['label' => 'Rendered', 'value' => '0'],
                ],
                'blocks' => [
                    [
                        'type' => 'kv',
                        'section' => null,
                        'rows' => [
                            ['label' => 'Status', 'value' => 'No template was rendered during this request.'],
                        ],
                    ],
                ],
            ];
        }

        return [
            'title' => 'Views',
            'badge' => $data['errorCount'] > 0 ? (string) $data['errorCount'] : null,
            'badgeType' => 'alert',
            'metrics' => [
                ['label' => 'Rendered', 'value' => (string) $data['total']],
                ['label' => 'Total time', 'value' => (string) $data['totalDuration'], 'unit' => 'ms'],
                ['label' => 'Errors', 'value' => (string) $data['errorCount']],
            ],
            'blocks' => [
                [
                    'type' => 'log-list',
                    'section' => null,
                    'rows' => array_map(
                        fn (array $r) => [
                            'time' => number_format($r['duration'], 2) . ' ms',
                            'channel' => 'twig',
                            'origin' => $r['error'] !== null ? 'ERROR' : '',
                            'message' => $r['template'],
                            'context' => $this->formatContext($r),
                        ],
                        $data['renders']
                    ),
                ],
            ],
        ];
    }

    /**
     * @param array{params: list<string>, error: string|null} $r
     */
    private function formatContext(array $r): string
    {
        $lines = [];

        if ($r['params'] !== []) {
            $lines[] = 'Variables passed: ' . implode(', ', $r['params']);
        }

        if ($r['error'] !== null) {
            $lines[] = '';
            $lines[] = 'Error: ' . $r['error'];
        }

        return implode("\n", $lines);
    }
}