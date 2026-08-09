<?php

declare(strict_types=1);

namespace Neo\Core\Http\Response\Collector;

use Neo\Core\Http\Response\Types\Response;
use Neo\Core\Profiler\Interface\CollectorInterface;
use Neo\Core\Profiler\Interface\ResponseAwareCollectorInterface;

final class ResponseCollector implements CollectorInterface, ResponseAwareCollectorInterface
{
    private const array MASKED_HEADERS = ['set-cookie'];

    private ?Response $response = null;

    public function setResponse(Response $response): void
    {
        $this->response = $response;
    }

    public function getName(): string
    {
        return 'response';
    }

    public function collect(): array
    {
        if ($this->response === null) {
            return [
                'available' => false,
                'statusCode' => null,
                'headers' => [],
                'contentType' => null,
                'contentLength' => 0,
            ];
        }

        $headers = $this->response->getHeaders();
        $content = $this->response->getContent();

        return [
            'available' => true,
            'statusCode' => method_exists($this->response, 'getStatusCode') ? $this->response->getStatusCode() : null,
            'headers' => $headers,
            'contentType' => $headers['Content-Type'] ?? null,
            'contentLength' => is_string($content) ? strlen($content) : 0,
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
            'label' => 'Response',
            'value' => '',
            'badge' => null,
        ];
    }

    public function profilerData(): array
    {
        $data = $this->collect();

        if (!$data['available']) {
            return [
                'title' => 'Response',
                'badge' => null,
                'group' => 'Http',
                'blocks' => [
                    [
                        'type' => 'kv',
                        'section' => null,
                        'rows' => [
                            ['label' => 'Status', 'value' => 'No response captured for this request.'],
                        ],
                    ],
                ],
            ];
        }

        return [
            'title' => 'Response',
            'group' => 'Http',
            'badge' => null,
            'blocks' => [
                [
                    'type' => 'kv',
                    'section' => null,
                    'rows' => [
                        ['label' => 'Status code', 'value' => $data['statusCode'] !== null ? (string) $data['statusCode'] : 'n/a'],
                        ['label' => 'Content type', 'value' => $data['contentType'] ?? 'n/a'],
                        ['label' => 'Content length', 'value' => $this->formatBytes($data['contentLength'])],
                    ],
                ],
                [
                    'type' => 'table',
                    'section' => 'Headers',
                    'columns' => ['Name', 'Value'],
                    'rows' => $this->maskedHeaderRows($data['headers']),
                ],
            ],
        ];
    }

    /**
     * @param array<string, string> $headers
     * @return list<list<string>>
     */
    private function maskedHeaderRows(array $headers): array
    {
        $rows = [];

        foreach ($headers as $name => $value) {
            $isSensitive = in_array(strtolower($name), self::MASKED_HEADERS, true);
            $rows[] = [$name, $isSensitive ? '••••••••' : $value];
        }

        return $rows;
    }

    private function formatBytes(int $bytes): string
    {
        if ($bytes >= 1024 * 1024) {
            return round($bytes / 1024 / 1024, 2) . ' MB';
        }

        if ($bytes >= 1024) {
            return round($bytes / 1024, 1) . ' KB';
        }

        return $bytes . ' B';
    }
}