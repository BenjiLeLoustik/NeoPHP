<?php

declare(strict_types=1);

namespace Neo\Core\Utils\Notification\Collector;

use Neo\Core\Profiler\Interface\CollectorInterface;
use Neo\Core\Tools\Debug\Dumper;
use Neo\Core\Utils\Notification\NotificationManager;

final class NotificationCollector implements CollectorInterface
{
    public function getName(): string
    {
        return 'notification';
    }

    public function collect(): array
    {
        $log = NotificationManager::getLog();
        $failedCount = count(array_filter($log, static fn (array $n) => $n['result'] === 'failed' || $n['error'] !== null));

        return [
            'total' => count($log),
            'failedCount' => $failedCount,
            'notifications' => $log,
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
            'label' => 'Notifications',
            'value' => '',
            'badge' => null,
        ];
    }

    public function profilerData(): array
    {
        $data = $this->collect();

        if ($data['total'] === 0) {
            return [
                'title' => 'Notifications',
                'badge' => null,
                'metrics' => [
                    ['label' => 'Sent', 'value' => '0'],
                ],
                'blocks' => [
                    [
                        'type' => 'kv',
                        'section' => null,
                        'rows' => [
                            ['label' => 'Status', 'value' => 'No notification was sent during this request.'],
                        ],
                    ],
                ],
            ];
        }

        $byResult = [];
        foreach ($data['notifications'] as $n) {
            $key = $n['error'] !== null ? 'failed' : $n['result'];
            $byResult[$key][] = $n;
        }

        $order = ['success', 'partial', 'skipped', 'failed'];
        $tabs = [];

        foreach ($order as $key) {
            if (!isset($byResult[$key])) {
                continue;
            }

            $entries = $byResult[$key];

            $tabs[] = [
                'label' => ucfirst($key),
                'badge' => (string) count($entries),
                'badgeType' => $key === 'failed' ? 'alert' : 'neutral',
                'blocks' => [
                    [
                        'type' => 'log-list',
                        'section' => null,
                        'messageLabel' => 'Channel',
                        'rows' => array_map(
                            fn (array $n) => [
                                'time' => number_format($n['duration'], 2) . ' ms',
                                'channel' => strtoupper($n['result']),
                                'origin' => $n['error'] !== null ? 'ERROR' : '',
                                'message' => $this->shortClass($n['channel']),
                                'context' => $this->formatContext($n),
                            ],
                            $entries
                        ),
                    ],
                ],
            ];
        }

        return [
            'title' => 'Notifications',
            'badge' => $data['failedCount'] > 0 ? (string) $data['failedCount'] : null,
            'badgeType' => 'alert',
            'metrics' => [
                ['label' => 'Sent', 'value' => (string) $data['total']],
                ['label' => 'Failed', 'value' => (string) $data['failedCount']],
            ],
            'blocks' => [
                ['type' => 'tabs', 'section' => null, 'tabs' => $tabs],
            ],
        ];
    }

    /**
     * @param array{channel: class-string, params: array<string, mixed>, template: string, result: string, error: string|null} $n
     * @return array{raw: true, html: string}
     */
    private function formatContext(array $n): array
    {
        $html = $n['params'] !== []
            ? new Dumper()->render([$n['params']], false)
            : '<p class="empty-state">No params.</p>';

        $meta = [];
        $meta[] = 'channel: ' . $n['channel'];

        if ($n['template'] !== '') {
            $meta[] = 'template: ' . $n['template'];
        }

        $meta[] = 'result: ' . $n['result'];

        if ($n['error'] !== null) {
            $meta[] = 'error: ' . $n['error'];
        }

        $html .= '<div style="color:var(--text-faint);font-family:var(--mono);font-size:0.76rem;margin-top:0.6rem;white-space:pre-wrap;">'
            . htmlspecialchars(implode("\n", $meta), ENT_QUOTES, 'UTF-8') . '</div>';

        return ['raw' => true, 'html' => $html];
    }

    private function shortClass(string $fqcn): string
    {
        $pos = strrpos($fqcn, '\\');

        return $pos === false ? $fqcn : substr($fqcn, $pos + 1);
    }
}