# Profiler

The `Profiler` module is a visual debugging tool built into NeoPHP. It collects request metrics (duration, memory, SQL queries, events, routes, logs, authenticated user...) and displays them in a floating debug bar automatically injected into the HTML of responses, only in the `dev` environment.

---

## Summary

1. [Overview](#overview)
2. [ProfilerModule](#profilermodule)
3. [ProfilerManager](#profilermanager)
4. [CollectorInterface](#collectorinterface)
5. [The Debug Bar (Toolbar)](#the-debug-bar-toolbar)
6. [ProfilerResponseListener](#profilerresponselistener)
7. [Creating a Custom Collector](#creating-a-custom-collector)
8. [Activation and Conditions](#activation-and-conditions)

---

## Overview

```
HTTP Request
     │
     ▼
ProfilerModule.init()
     ├── ProfilerManager::getInstance()
     ├── registerCollectors()   ← auto-scan of every *Collector.php
     ├── new Toolbar($profiler)
     └── EventDispatcher → ProfilerResponseListener

HTML Response
     │
     ▼
ProfilerResponseListener.onResponse()
     └── Toolbar.render()  →  injected before </body>
```

---

## ProfilerModule

File: `ProfilerModule.php`

### Declared Dependencies

```php
public function dependencies(): array
{
    return [
        ResponseModule::class,
        EventModule::class,
        RouterModule::class,
        AuthModule::class,
        TranslationModule::class,
        ConfigModule::class,
    ];
}
```

### Activation Conditions

The profiler only activates if **every** one of these conditions is met:

1. Execution is not in CLI (`php_sapi_name() !== 'cli'`).
2. The `environment` key in `config/app.php` equals `'dev'`.

```php
// Inside ProfilerModule::init()
$env = $container->get('profiler.configModule')->from('app')->get('environment') ?? 'prod';
if ($env !== 'dev') {
    return $profiler; // immediate return, no debug bar
}
```

When activated, the `NEO_PROFILER_ENABLED` constant is set to `true`.

### Automatic Collector Discovery

The module recursively scans the entire `neo/Core/` directory looking for files whose name ends with `Collector.php`. Every class found that implements `CollectorInterface` is instantiated through the DI container and added to the `ProfilerManager`.

```php
// Search pattern
'/^.+Collector\.php$/i'
```

If the collector also implements `CollectorAwareInterface`, its `boot(Container $container)` method is called to allow advanced initialization (e.g. attaching event listeners).

---

## ProfilerManager

File: `ProfilerManager.php`

`ProfilerManager` is a **singleton** that centralizes metric collection. It is globally accessible via `ProfilerManager::getInstance()`.

### Time and Memory Initialization

At construction time, the manager uses the framework's global constants if they are defined, otherwise it falls back to the current values:

```php
$this->startTime = defined('NEO_START_TIME')
    ? NEO_START_TIME
    : microtime(true);

$this->startMemory = defined('NEO_START_MEMORY')
    ? NEO_START_MEMORY
    : memory_get_usage(true);
```

### Public API

```php
// Singleton
$profiler = ProfilerManager::getInstance();
ProfilerManager::reset(); // resets for tests

// Collectors
$profiler->addCollector(CollectorInterface $collector): void;
$profiler->getCollector('sql'): ?CollectorInterface;
$profiler->getCollectors(): array; // ['sql' => ..., 'router' => ...]

// Global metrics
$profiler->getTotalDuration(): float;  // in milliseconds
$profiler->getPeakMemory(): int;       // in bytes (peak)
$profiler->getStartTime(): float;
$profiler->getStartMemory(): int;

// Full collection
$data = $profiler->collect();
// Returns: ['duration' => 42.3, 'memory' => 2097152, 'sql' => [...], 'router' => [...], ...]
```

### Data Collection

```php
public function collect(): array
{
    $data = [
        'duration' => round($this->getTotalDuration(), 2), // ms
        'memory'   => $this->getPeakMemory(),              // bytes
    ];

    foreach ($this->collectors as $name => $collector) {
        $data[$name] = $collector->collect();
    }

    return $data;
}
```

---

## CollectorInterface

File: `Interface/CollectorInterface.php`

Every metric collector must implement this interface:

```php
namespace Neo\Core\Profiler\Interface;

interface CollectorInterface
{
    /**
     * Unique identifier of the collector (used as the key in the data).
     */
    public function getName(): string;

    /**
     * Collects and returns the raw data.
     * @return array<string, mixed>
     */
    public function collect(): array;

    /**
     * HTML rendering of the tab in the debug bar.
     * @param array<string, mixed> $data
     */
    public function renderTab(array $data): string;

    /**
     * HTML rendering of the expandable panel (details).
     * @param array<string, mixed> $data
     */
    public function renderPanel(array $data): string;
}
```

### Optional Interface: CollectorAwareInterface

If a collector needs access to the DI container at initialization time (to attach listeners, etc.), it can implement `CollectorAwareInterface`:

```php
interface CollectorAwareInterface
{
    public function boot(Container $container): void;
}
```

---

## The Debug Bar (Toolbar)

File: `Toolbar/Toolbar.php`

`Toolbar` is a `readonly` class that generates the complete HTML of the debug bar from the data collected by `ProfilerManager`.

### Visual Structure

```
┌─────────────────────────────────────────────────────────────────────┐
│ [Neo] │ Response: 42ms │ Memory: 8.2 MB │ [SQL: 3] │ [Router] │ ... │
└─────────────────────────────────────────────────────────────────────┘
                      ↕ click on a tab
┌─────────────────────────────────────────────────────────────────────┐
│ [SQL] [Router] [Auth] [Events] [Logs]                         [✕]  │
│                                                                     │
│  (content of the selected panel)                                    │
└─────────────────────────────────────────────────────────────────────┘
```

### Duration Color

Execution duration is colorized based on thresholds:

| Duration | Color |
|---|---|
| < 200 ms | Green (`#4ade80`) |
| 200 - 499 ms | Orange (`#fb923c`) |
| >= 500 ms | Red (`#f87171`) |

### State Persistence

The bar remembers its open/closed state in the browser's `localStorage` under the `neo_bar_visible` key. The state is restored on every page load.

### Rendering

```php
$toolbar = new Toolbar($profiler);
$html = $toolbar->render(); // returns the full HTML + CSS + inline JS
```

---

## ProfilerResponseListener

File: `Listener/ProfilerResponseListener.php`

This listener listens for the `ResponseEvent` and injects the debug bar into the HTML response.

### Injection Conditions

The listener **does not modify** the response if:

- The response is a `RedirectResponse`.
- The response is a `JsonResponse`.
- The `Content-Type` does not contain `text/html`.

### Injection Strategy

```php
public function onResponse(ResponseEvent $event): void
{
    // ...checks...

    $toolbar = $this->toolbar->render();

    if (str_contains($content, '</body>')) {
        // Clean injection before the closing body tag
        $content = str_replace('</body>', $toolbar . '</body>', $content);
    } else {
        // Fallback: appended at the end of the content
        $content .= $toolbar;
    }

    $response->setContent($content);
    $event->setResponse($response);
}
```

---

## Creating a Custom Collector

To add a collector to the profiler, simply create a class whose name ends with `Collector.php` inside any subfolder of `neo/Core/`. It will be automatically discovered and registered.

```php
<?php
declare(strict_types=1);

namespace Neo\Core\MyFeature\Collector;

use Neo\Core\Profiler\Interface\CollectorInterface;

class MyFeatureCollector implements CollectorInterface
{
    private array $events = [];

    public function record(string $message): void
    {
        $this->events[] = $message;
    }

    public function getName(): string
    {
        return 'myfeature'; // key in the collected data
    }

    public function collect(): array
    {
        return [
            'count'  => count($this->events),
            'events' => $this->events,
        ];
    }

    public function renderTab(array $data): string
    {
        $count = $data['count'] ?? 0;
        return sprintf(
            '<div class="n-tab" onclick="neoSwitch(\'myfeature\')">
                <span class="n-label">MyFeature</span>
                <span class="n-badge">%d</span>
            </div>',
            $count
        );
    }

    public function renderPanel(array $data): string
    {
        if (empty($data['events'])) {
            return '<div class="n-empty">No events.</div>';
        }

        $rows = '';
        foreach ($data['events'] as $event) {
            $rows .= '<tr><td>' . htmlspecialchars($event) . '</td></tr>';
        }

        return '<table><thead><tr><th>Message</th></tr></thead><tbody>' . $rows . '</tbody></table>';
    }
}
```

### Using the Collector from Another Service

Since `ProfilerManager` is a singleton, any service can access it to record data:

```php
if (defined('NEO_PROFILER_ENABLED') && NEO_PROFILER_ENABLED) {
    $collector = ProfilerManager::getInstance()->getCollector('myfeature');
    $collector?->record('Something happened');
}
```

---

## Activation and Conditions

| Condition | Behavior |
|---|---|
| `environment = prod` | Profiler disabled, `ProfilerManager` returned with no collectors |
| `environment = dev` | Profiler enabled, bar injected |
| CLI execution | Profiler unconditionally disabled |
| JSON response or redirect | Bar not injected even in `dev` |
| Class in `\Tests\` or `\Fixture\` | Ignored by the collector scan |