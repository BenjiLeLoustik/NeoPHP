<?php

declare(strict_types=1);

namespace Neo\Core\View\Collector;

use Neo\Core\Profiler\Interface\CollectorInterface;
use Neo\Core\Tools\Debug\Dumper;
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
                    'timeLabel' => 'Duration',
                    'messageLabel' => 'Template',
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
     * @param array{params: array<string, mixed>, error: string|null} $r
     * @return string|array{raw: true, html: string}
     */
    private function formatContext(array $r): string|array
    {
        if ($r['params'] === [] && $r['error'] === null) {
            return '';
        }

        if ($r['error'] !== null) {
            $html = new Dumper()->render([$r['params']], false);
            return [
                'raw' => true,
                'html' => '<div style="color:#dc2626;font-family:var(--mono);font-size:0.8rem;margin-bottom:0.5rem;">Error: '
                    . htmlspecialchars($r['error'], ENT_QUOTES, 'UTF-8') . '</div>' . $html,
            ];
        }

        return [
            'raw' => true,
            'html' => new Dumper()->render([$r['params']], false),
        ];
    }
}