<?php

declare(strict_types=1);

namespace Neo\Core\Http\Client\Cookie\Collector;

use Neo\Core\DI\Container;
use Neo\Core\Http\Client\Cookie\Cookie;
use Neo\Core\Profiler\Interface\CollectorInterface;

final class CookieCollector implements CollectorInterface
{
    private const array MASKED_NAMES = ['password', 'secret', 'token', 'session'];

    public function __construct(private readonly Container $container)
    {
    }

    public function getName(): string
    {
        return 'cookies';
    }

    public function collect(): array
    {
        /** @var Cookie $cookie */
        $cookie = $this->container->get(Cookie::class);
        $data = $cookie->all();

        return [
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
        return [
            'label' => 'Cookie',
            'value' => '',
            'badge' => null,
        ];
    }

    public function profilerData(): array
    {
        $data = $this->collect();

        return [
            'title' => 'Cookies',
            'group' => 'Http',
            'badge' => null,
            'metrics' => [
                ['label' => 'Cookies', 'value' => (string) $data['count']],
            ],
            'blocks' => [
                [
                    'type' => 'table',
                    'section' => null,
                    'columns' => ['Name', 'Value'],
                    'rows' => array_map(
                        fn (string $name, string $value) => [$name, $this->maskIfSensitive($name, $value)],
                        array_keys($data['data']),
                        array_values($data['data'])
                    ),
                ],
            ],
        ];
    }

    private function maskIfSensitive(string $name, string $value): string
    {
        $lower = strtolower($name);

        foreach (self::MASKED_NAMES as $masked) {
            if (str_contains($lower, $masked)) {
                return '••••••••';
            }
        }

        return $value;
    }
}