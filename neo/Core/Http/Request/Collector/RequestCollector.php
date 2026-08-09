<?php

declare(strict_types=1);

namespace Neo\Core\Http\Request\Collector;

use Neo\Core\Http\Request\Request;
use Neo\Core\Profiler\Interface\CollectorInterface;
use Neo\Core\Tools\Debug\Dumper;

final class RequestCollector implements CollectorInterface
{
    public function __construct(private readonly Request $request)
    {
    }

    public function getName(): string
    {
        return 'request';
    }

    public function collect(): array
    {
        return [
            'method' => $this->request->getMethod(),
            'path' => $this->request->getPath(),
            'fullUrl' => $this->request->getFullUrl(),
            'ip' => $this->request->getIp(),
            'userAgent' => $this->request->getUserAgent(),
            'headers' => $this->request->headers(),
            'query' => $this->request->allQuery(),
            'body' => $this->request->allBody(),
            'files' => $this->request->allFiles(),
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
            'label' => 'Request',
            'value' => '',
            'badge' => null,
        ];
    }

    public function profilerData(): array
    {
        $data = $this->collect();

        return [
            'title' => 'Request',
            'badge' => null,
            'group' => 'Http',
            'blocks' => [
                [
                    'type' => 'kv',
                    'section' => null,
                    'rows' => [
                        ['label' => 'Method', 'value' => $data['method']],
                        ['label' => 'Path', 'value' => $data['path']],
                        ['label' => 'Full URL', 'value' => $data['fullUrl']],
                        ['label' => 'IP', 'value' => $data['ip'] ?? 'n/a'],
                        ['label' => 'User agent', 'value' => $data['userAgent'] ?? 'n/a'],
                    ],
                ],
                [
                    'type' => 'raw-html',
                    'section' => 'Headers',
                    'html' => $this->dumpOrEmpty($data['headers']),
                ],
                [
                    'type' => 'raw-html',
                    'section' => 'Query parameters',
                    'html' => $this->dumpOrEmpty($data['query']),
                ],
                [
                    'type' => 'raw-html',
                    'section' => 'Body',
                    'html' => $this->dumpOrEmpty($data['body']),
                ],
                [
                    'type' => 'raw-html',
                    'section' => 'Files',
                    'html' => $this->dumpOrEmpty($data['files']),
                ],
            ],
        ];
    }

    /**
     * @param array<array-key, mixed> $data
     */
    private function dumpOrEmpty(array $data): string
    {
        if ($data === []) {
            return '<p class="empty-state">No data.</p>';
        }

        return new Dumper()->render([$data], false);
    }
}