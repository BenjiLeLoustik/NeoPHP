<?php

declare(strict_types=1);

namespace Neo\Core\Http\Client\Session\Collector;

use Neo\Core\DI\Container;
use Neo\Core\DI\Exception\ContainerException;
use Neo\Core\Http\Client\Session\Session;
use Neo\Core\Profiler\Interface\CollectorInterface;
use Neo\Core\Tools\Debug\Dumper;
use ReflectionException;

final class SessionCollector implements CollectorInterface
{
    public function __construct(private readonly Container $container)
    {
    }

    public function getName(): string
    {
        return 'session';
    }

    /**
     * @throws ReflectionException
     * @throws ContainerException
     */
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
        $data = $this->collect();

        return [
            'label' => 'Session',
            'value' => (string) $data['count'],
            'badge' => null,
        ];
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

        $simple = [];
        $complex = [];

        foreach ($data['data'] as $key => $value) {
            if (is_array($value) || is_object($value)) {
                $complex[$key] = $value;
                continue;
            }

            $simple[$key] = $value;
        }

        if ($simple !== []) {
            $blocks[] = [
                'type' => 'table',
                'section' => 'Attributes',
                'columns' => ['Key', 'Value'],
                'rows' => array_map(
                    fn (string $key, mixed $value) => [$key, $this->formatScalar($value)],
                    array_keys($simple),
                    array_values($simple)
                ),
            ];
        }

        if ($complex !== []) {
            $blocks[] = [
                'type' => 'log-list',
                'section' => 'Complex attributes',
                'timeLabel' => 'Type',
                'messageLabel' => 'Key',
                'rows' => array_map(
                    fn (string $key, mixed $value) => [
                        'time' => is_array($value) ? 'array' : 'object',
                        'channel' => 'session',
                        'origin' => '',
                        'message' => $key,
                        'context' => ['raw' => true, 'html' => new Dumper()->render([$value], false)],
                    ],
                    array_keys($complex),
                    array_values($complex)
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

    private function formatScalar(mixed $value): string
    {
        return match (true) {
            $value === null => 'null',
            is_bool($value) => $value ? 'true' : 'false',
            default => (string) $value,
        };
    }
}