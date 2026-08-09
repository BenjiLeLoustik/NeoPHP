<?php

declare(strict_types=1);

namespace Neo\Core\Http\Client\Flash\Collector;

use Neo\Core\DI\Container;
use Neo\Core\Http\Client\Flash\Flash;
use Neo\Core\Profiler\Interface\CollectorInterface;

final class FlashCollector implements CollectorInterface
{
    public function __construct(private readonly Container $container)
    {
    }

    public function getName(): string
    {
        return 'flash';
    }

    public function collect(): array
    {
        /** @var Flash $flash */
        $flash = $this->container->get(Flash::class);

        return [
            'messages' => $flash->peek(),
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
            'label' => 'Flash',
            'value' => (string) count($data['messages']),
            'badge' => null,
        ];
    }

    public function profilerData(): array
    {
        $data = $this->collect();

        if ($data['messages'] === []) {
            return [
                'title' => 'Flash',
                'group' => 'Http',
                'badge' => null,
                'metrics' => [
                    ['label' => 'Pending', 'value' => '0'],
                ],
                'blocks' => [
                    [
                        'type' => 'kv',
                        'section' => null,
                        'rows' => [
                            ['label' => 'Status', 'value' => 'No flash message pending in session.'],
                        ],
                    ],
                ],
            ];
        }

        $byType = [];
        foreach ($data['messages'] as $message) {
            $byType[$message['type']][] = $message;
        }

        $tabs = [
            [
                'label' => 'All',
                'badge' => (string) count($data['messages']),
                'badgeType' => 'neutral',
                'blocks' => [
                    [
                        'type' => 'table',
                        'section' => null,
                        'columns' => ['Type', 'Message'],
                        'rows' => array_map(
                            static fn (array $m) => [$m['type'], $m['message']],
                            $data['messages']
                        ),
                    ],
                ],
            ],
        ];

        foreach ($byType as $type => $messages) {
            $tabs[] = [
                'label' => ucfirst($type),
                'badge' => (string) count($messages),
                'badgeType' => $this->badgeTypeFor($type),
                'blocks' => [
                    [
                        'type' => 'table',
                        'section' => null,
                        'columns' => ['Message'],
                        'rows' => array_map(
                            static fn (array $m) => [$m['message']],
                            $messages
                        ),
                    ],
                ],
            ];
        }

        return [
            'title' => 'Flash',
            'group' => 'Http',
            'badge' => null,
            'metrics' => [
                ['label' => 'Pending', 'value' => (string) count($data['messages'])],
            ],
            'blocks' => [
                ['type' => 'tabs', 'section' => null, 'tabs' => $tabs],
            ],
        ];
    }

    private function badgeTypeFor(string $type): string
    {
        return in_array($type, ['error', 'warning'], true) ? 'alert' : 'neutral';
    }
}