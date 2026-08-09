<?php

declare(strict_types=1);

namespace Neo\Core\Http\HttpClient\Collector;

use Neo\Core\Http\HttpClient\HttpClientManager;
use Neo\Core\Profiler\Interface\CollectorInterface;

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
        return [];
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
                [
                    'type' => 'log-list',
                    'section' => null,
                    'rows' => array_map(
                        fn (array $r) => [
                            'time' => number_format($r['duration'], 2) . ' ms',
                            'channel' => $r['method'],
                            'origin' => $r['error'] !== null ? 'ERROR' : (string) ($r['statusCode'] ?? ''),
                            'message' => $r['url'],
                            'context' => $this->formatContext($r),
                        ],
                        $data['requests']
                    ),
                ],
            ],
        ];
    }

    /**
     * @param array{requestHeaders: list<string>, requestBody: string|null, responseHeaders: array<string, list<string>>, error: string|null} $r
     */
    private function formatContext(array $r): string
    {
        $lines = [];

        if ($r['requestHeaders'] !== []) {
            $lines[] = 'Request headers:';
            foreach ($r['requestHeaders'] as $header) {
                $lines[] = '  ' . $header;
            }
        }

        if ($r['requestBody'] !== null && $r['requestBody'] !== '') {
            $lines[] = '';
            $lines[] = 'Request body:';
            $lines[] = $r['requestBody'];
        }

        if ($r['responseHeaders'] !== []) {
            $lines[] = '';
            $lines[] = 'Response headers:';
            foreach ($r['responseHeaders'] as $name => $values) {
                $lines[] = '  ' . $name . ': ' . implode(', ', $values);
            }
        }

        if ($r['error'] !== null) {
            $lines[] = '';
            $lines[] = 'Error: ' . $r['error'];
        }

        return implode("\n", $lines);
    }
}