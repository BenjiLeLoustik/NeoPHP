<?php
declare(strict_types=1);

namespace Neo\Core\Profiler\Collector;

use Neo\Core\Event\EventDispatcher;

class EventCollector implements CollectorInterface
{
    private array $events = [];

    public function __construct(private readonly EventDispatcher $dispatcher) {}

    public function getName(): string
    {
        return 'events';
    }

    public function record(string $eventClass, array $listeners, float $duration): void
    {
        $this->events[] = [
            'event' => $eventClass,
            'listeners' => $listeners,
            'duration' => round($duration, 3),
        ];
    }

    public function collect(): array
    {
        $registered = [];
        foreach ($this->dispatcher->getListeners() as $event => $list) {
            $registered[$event] = array_column($list, 'class');
        }

        return [
            'count' => count($this->events),
            'dispatched' => $this->events,
            'registered' => $registered,
        ];
    }
}