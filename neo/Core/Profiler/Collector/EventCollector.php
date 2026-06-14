<?php
declare(strict_types=1);

namespace Neo\Core\Profiler\Collector;

use Neo\Core\Event\EventDispatcher;

class EventCollector implements CollectorInterface
{
    /** @var array<int, array{event: string, listeners: array<int, string>, duration: float}> */
    private array $events = [];

    public function __construct(
        private readonly EventDispatcher $dispatcher
    ) {}

    public function getName(): string
    {
        return 'events';
    }

    /**
     * @param array<int, string> $listeners
     */
    public function record(string $eventClass, array $listeners, float $duration): void
    {
        $this->events[] = [
            'event' => $eventClass,
            'listeners' => $listeners,
            'duration' => round($duration, 3),
        ];
    }

    /**
     * @return array<string, mixed>
     */
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