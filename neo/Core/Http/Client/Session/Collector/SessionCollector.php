<?php

declare(strict_types=1);

namespace Neo\Core\Http\Client\Session\Collector;

use Neo\Core\DI\Container;
use Neo\Core\Http\Client\Session\Session;
use Neo\Core\Profiler\Interface\CollectorInterface;

final class SessionCollector implements CollectorInterface
{
    private const array MASKED_KEYS = ['password', 'secret', 'token'];

    public function __construct(private readonly Container $container)
    {
    }

    public function getName(): string
    {
        return 'session';
    }

    public function collect(): array
    {
        /** @var Session $session */
        $session = $this->container->get(Session::class);
        $data = $session->all();

        return [
            'id' => session_id() ?: null,
            'name' => session_name() ?: null,
            'count' => count($data),
            'data' => $data,
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

        $blocks = [
            [
                'type' => 'kv',
                'section' => null,
                'rows' => [
                    ['label' => 'Session ID', 'value' => $data['id'] ?? 'n/a'],
                    ['label' => 'Session name', 'value' => $data['name'] ?? 'n/a'],
                    ['label' => 'Attributes count', 'value' => (string) $data['count']],
                ],
            ],
        ];

        if ($data['count'] > 0) {
            $blocks[] = [
                'type' => 'table',
                'section' => 'Attributes',
                'columns' => ['Key', 'Value'],
                'rows' => array_map(
                    fn (string $key, mixed $value) => [$key, $this->formatValue($key, $value)],
                    array_keys($data['data']),
                    array_values($data['data'])
                ),
            ];
        }

        return [
            'title' => 'Session',
            'group' => 'Http',
            'badge' => null,
            'metrics' => [
                ['label' => 'Attributes', 'value' => (string) $data['count']],
            ],
            'blocks' => $blocks,
        ];
    }

    private function formatValue(string $key, mixed $value): string
    {
        if ($this->isMasked($key)) {
            return '••••••••';
        }

        return match (true) {
            $value === null => 'null',
            is_bool($value) => $value ? 'true' : 'false',
            is_scalar($value) => (string) $value,
            is_array($value) => json_encode($value, JSON_UNESCAPED_UNICODE) ?: '[]',
            is_object($value) => $value::class,
            default => (string) $value,
        };
    }

    private function isMasked(string $key): bool
    {
        $lower = strtolower($key);

        foreach (self::MASKED_KEYS as $masked) {
            if (str_contains($lower, $masked)) {
                return true;
            }
        }

        return false;
    }
}