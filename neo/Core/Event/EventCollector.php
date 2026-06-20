<?php
declare(strict_types=1);

namespace Neo\Core\Event;

use Neo\Core\Profiler\Collector\CollectorInterface;

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

    public function renderTab(array $data): string
    {
        $count = $data['count'] ?? 0;

        return <<<HTML
<div class="n-tab" onclick="neoSwitch('events')" title="Événements">
    <span class="n-label">Events</span>
    <span class="n-value">{$count}</span>
</div>
HTML;
    }

    /**
     * @param array<string, mixed> $data
     */
    public function renderPanel(array $data): string
    {
        $dispatched = $data['dispatched'] ?? [];
        $registered = $data['registered'] ?? [];

        $dispatchedHtml = empty($dispatched)
            ? '<p class="n-empty">No events dispatched.</p>'
            : $this->renderDispatched($dispatched);

        $registeredHtml = empty($registered)
            ? '<p class="n-empty">No listeners registered.</p>'
            : $this->renderRegistered($registered);

        return <<<HTML
<p class="n-section-title">Dispatched ({$data['count']})</p>
{$dispatchedHtml}

<p class="n-section-title">Registered listeners</p>
{$registeredHtml}
HTML;
    }

    /**
     * @param array<int, array<string, mixed>> $dispatched
     */
    private function renderDispatched(array $dispatched): string
    {
        $rows = '';
        foreach ($dispatched as $e) {
            $event = htmlspecialchars($e['event']);
            $listeners = htmlspecialchars(implode(', ', $e['listeners']));
            $ms = htmlspecialchars((string) $e['duration']);

            $rows .= <<<HTML
<tr>
    <td class="n-event">{$event}</td>
    <td class="n-origin">{$listeners}</td>
    <td class="n-ms">{$ms} ms</td>
</tr>
HTML;
        }

        return <<<HTML
<table>
    <thead>
        <tr><th>Event</th><th>Listeners</th><th style="text-align:right">Time</th></tr>
    </thead>
    <tbody>{$rows}</tbody>
</table>
HTML;
    }

    /**
     * @param array<string, array<int, string>> $registered
     */
    private function renderRegistered(array $registered): string
    {
        $rows = '';
        foreach ($registered as $event => $listeners) {
            $e = htmlspecialchars($event);
            $l = htmlspecialchars(implode(', ', $listeners));

            $rows .= <<<HTML
<tr>
    <td class="n-event">{$e}</td>
    <td class="n-origin">{$l}</td>
</tr>
HTML;
        }

        return <<<HTML
<table>
    <thead>
        <tr><th>Event</th><th>Listeners</th></tr>
    </thead>
    <tbody>{$rows}</tbody>
</table>
HTML;
    }
}