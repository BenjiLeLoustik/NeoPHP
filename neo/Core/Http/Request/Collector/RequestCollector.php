<?php

declare(strict_types=1);

namespace Neo\Core\Http\Request\Collector;

use Neo\Core\Http\Request\Request;
use Neo\Core\Profiler\Interface\CollectorInterface;

final class RequestCollector implements CollectorInterface
{
    private const array MASKED_HEADERS = ['authorization', 'cookie', 'x-api-key'];

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
                    'type' => 'table',
                    'section' => 'Headers',
                    'columns' => ['Name', 'Value'],
                    'rows' => $this->maskedHeaderRows($data['headers']),
                ],
                [
                    'type' => 'table',
                    'section' => 'Query parameters',
                    'columns' => ['Name', 'Value'],
                    'rows' => $this->flatRows($data['query']),
                ],
                [
                    'type' => 'table',
                    'section' => 'Body',
                    'columns' => ['Name', 'Value'],
                    'rows' => $this->flatRows($data['body']),
                ],
                [
                    'type' => 'table',
                    'section' => 'Files',
                    'columns' => ['Field', 'Filename', 'Size', 'Type'],
                    'rows' => $this->fileRows($data['files']),
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

    /**
     * @param array<string, mixed> $data
     * @return list<list<string>>
     */
    private function flatRows(array $data): array
    {
        $rows = [];

        foreach ($data as $key => $value) {
            $rows[] = [(string) $key, $this->stringify($value)];
        }

        return $rows;
    }

    /**
     * @param array<string, array<string, mixed>> $files
     * @return list<list<string>>
     */
    private function fileRows(array $files): array
    {
        $rows = [];

        foreach ($files as $field => $file) {
            $rows[] = [
                $field,
                (string) ($file['name'] ?? 'n/a'),
                isset($file['size']) ? $this->formatBytes((int) $file['size']) : 'n/a',
                (string) ($file['type'] ?? 'n/a'),
            ];
        }

        return $rows;
    }

    private function formatBytes(int $bytes): string
    {
        if ($bytes >= 1024 * 1024) {
            return round($bytes / 1024 / 1024, 2) . ' MB';
        }

        return round($bytes / 1024, 1) . ' KB';
    }

    private function stringify(mixed $value): string
    {
        return match (true) {
            $value === null => 'null',
            is_bool($value) => $value ? 'true' : 'false',
            is_scalar($value) => (string) $value,
            is_array($value) => json_encode($value, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) ?: '[]',
            default => (string) $value,
        };
    }
}