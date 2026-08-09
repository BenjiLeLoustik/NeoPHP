<?php

declare(strict_types=1);

namespace Neo\Core\Http\Collector;

use Neo\Core\Http\HttpClient\HttpClientManager;
use Neo\Core\Profiler\Interface\CollectorInterface;
use Neo\Core\Tools\Debug\Dumper;

final class HttpClientCollector implements CollectorInterface
{
    public function getName(): string
    {
        return 'http-client';
    }

    public function collect(): array
    {
        $requests = HttpClientManager::getRequests();

        $totalDuration = array_sum(array_map(static fn (array $r) => $r['duration'], $requests));
        $errorCount = count(array_filter($requests, static fn (array $r) => $r['error'] !== null));

        return [
            'total' => count($requests),
            'totalDuration' => round($totalDuration, 2),
            'errorCount' => $errorCount,
            'requests' => $requests,
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
            'label' => 'Client',
            'value' => '',
            'badge' => null,
        ];
    }

    public function profilerData(): array
    {
        $data = $this->collect();

        if ($data['total'] === 0) {
            return [
                'title' => 'Client',
                'group' => 'Http',
                'badge' => null,
                'metrics' => [
                    ['label' => 'Requests', 'value' => '0'],
                ],
                'blocks' => [
                    [
                        'type' => 'kv',
                        'section' => null,
                        'rows' => [
                            ['label' => 'Status', 'value' => 'No outgoing HTTP request was made during this request.'],
                        ],
                    ],
                ],
            ];
        }

        $tabs = [];

        foreach ($data['requests'] as $i => $r) {
            $tabs[] = [
                'label' => $r['method'] . ' #' . ($i + 1),
                'badge' => $r['error'] !== null ? '!' : (string) ($r['statusCode'] ?? ''),
                'badgeType' => $this->badgeTypeFor($r),
                'blocks' => [
                    [
                        'type' => 'kv',
                        'section' => null,
                        'rows' => [
                            ['label' => 'URL', 'value' => $r['url']],
                            ['label' => 'Method', 'value' => $r['method']],
                            ['label' => 'Status', 'value' => $r['error'] !== null ? 'Transport error' : (string) ($r['statusCode'] ?? 'n/a')],
                            ['label' => 'Duration', 'value' => number_format($r['duration'], 2) . ' ms'],
                        ],
                    ],
                    [
                        'type' => 'raw-html',
                        'section' => 'Request headers',
                        'html' => $r['requestHeaders'] === [] ? '<p class="empty-state">No data.</p>' : new Dumper()->render([$r['requestHeaders']], false),
                    ],
                    [
                        'type' => 'raw-html',
                        'section' => 'Request body',
                        'html' => $this->renderBody($r['requestBody']),
                    ],
                    [
                        'type' => 'raw-html',
                        'section' => 'Response headers',
                        'html' => $r['responseHeaders'] === [] ? '<p class="empty-state">No data.</p>' : new Dumper()->render([$r['responseHeaders']], false),
                    ],
                    [
                        'type' => 'kv',
                        'section' => $r['error'] !== null ? 'Error' : null,
                        'rows' => $r['error'] !== null ? [['label' => 'Message', 'value' => $r['error']]] : [],
                    ],
                ],
            ];
        }

        return [
            'title' => 'Client',
            'group' => 'Http',
            'badge' => $data['errorCount'] > 0 ? (string) $data['errorCount'] : null,
            'badgeType' => 'alert',
            'metrics' => [
                ['label' => 'Requests', 'value' => (string) $data['total']],
                ['label' => 'Total time', 'value' => (string) $data['totalDuration'], 'unit' => 'ms'],
                ['label' => 'Errors', 'value' => (string) $data['errorCount']],
            ],
            'blocks' => [
                ['type' => 'tabs', 'section' => null, 'tabs' => $tabs],
            ],
        ];
    }

    /**
     * @param array{error: string|null, statusCode: int|null} $r
     */
    private function badgeTypeFor(array $r): string
    {
        if ($r['error'] !== null) {
            return 'alert';
        }

        return ($r['statusCode'] ?? 200) >= 400 ? 'alert' : 'neutral';
    }

    private function renderBody(?string $body): string
    {
        if ($body === null || $body === '') {
            return '<p class="empty-state">No data.</p>';
        }

        $decoded = json_decode($body, true);

        if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
            return new Dumper()->render([$decoded], false);
        }

        return new Dumper()->render([$body]);
    }
}