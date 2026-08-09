<?php

declare(strict_types=1);

namespace Neo\Core\Event\Collector;

use Neo\Core\Event\EventManager;
use Neo\Core\Profiler\Interface\CollectorInterface;

final class EventsCollector implements CollectorInterface
{
    public function __construct(private readonly EventManager $eventManager)
    {
    }

    public function getName(): string
    {
        return 'events';
    }

    public function collect(): array
    {
        $log = $this->eventManager->getDispatchLog();

        $totalListenersCalled = array_sum(array_map(
            static fn (array $entry) => count($entry['listeners']),
            $log
        ));

        return [
            'total' => count($log),
            'totalListenersCalled' => $totalListenersCalled,
            'dispatches' => $log,
        ];
    }

    public function inToolbar(): bool
    {
        return true;
    }

    public function inProfiler(): bool
    {
        return true;
    }

    public function toolbarData(): array
    {
        $data = $this->collect();

        return [
            'label' => 'Events',
            'value' => (string) $data['total'],
            'badge' => null,
        ];
    }

    public function profilerData(): array
    {
        $data = $this->collect();

        if ($data['total'] === 0) {
            return [
                'title' => 'Events',
                'badge' => null,
                'metrics' => [
                    ['label' => 'Dispatched', 'value' => '0'],
                ],
                'blocks' => [
                    [
                        'type' => 'kv',
                        'section' => null,
                        'rows' => [
                            ['label' => 'Status', 'value' => 'No event was dispatched during this request.'],
                        ],
                    ],
                ],
            ];
        }

        return [
            'title' => 'Events',
            'badge' => null,
            'metrics' => [
                ['label' => 'Dispatched', 'value' => (string) $data['total']],
                ['label' => 'Listeners called', 'value' => (string) $data['totalListenersCalled']],
            ],
            'blocks' => [
                [
                    'type' => 'table',
                    'section' => null,
                    'columns' => ['Event', 'Listeners', 'Stopped by', 'Duration'],
                    'rows' => array_map(
                        static fn (array $d) => [
                            $d['event'],
                            (string) count($d['listeners']),
                            $d['stoppedBy'] ?? ($d['stoppedEarly'] ? 'Yes (unresolved)' : 'No'),
                            $d['totalDuration'] . ' ms',
                        ],
                        $data['dispatches']
                    ),
                ],
                [
                    'type' => 'log-list',
                    'section' => 'Listener calls',
                    'rows' => $this->listenerRows($data['dispatches']),
                ],
            ],
        ];
    }

    /**
     * @param list<array{event: string, listeners: list<array{class: string, method: string, priority: int, duration: float, stoppedPropagation: bool}>, stoppedEarly: bool, stoppedBy: string|null, totalDuration: float}> $dispatches
     * @return list<array{time: string, channel: string, origin: string, message: string, context: string}>
     */
    private function listenerRows(array $dispatches): array
    {
        $rows = [];

        foreach ($dispatches as $dispatch) {
            foreach ($dispatch['listeners'] as $listener) {
                $rows[] = [
                    'time' => number_format($listener['duration'], 2) . ' ms',
                    'channel' => $dispatch['event'],
                    'origin' => 'priority ' . $listener['priority'],
                    'message' => $listener['class'] . '::' . $listener['method'] . '()'
                        . ($listener['stoppedPropagation'] ? ' [stopped propagation]' : ''),
                    'context' => '',
                ];
            }
        }

        return $rows;
    }
}