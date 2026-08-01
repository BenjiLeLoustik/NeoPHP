# Event System

The `Event` module implements an event system (pub/sub) for NeoPHP. It allows decoupling components by emitting events that listeners can intercept, with priority handling, propagation stopping, automatic discovery via PHP 8 attribute, a subscriber interface, and caching in production.

---

## Summary

1. [Module Files](#module-files)
2. [Creating an Event](#creating-an-event)
3. [ListenerRegistration](#listenerregistration)
4. [Creating a Listener with the `#[AsListener]` Attribute](#creating-a-listener-with-the-aslistener-attribute)
5. [Creating a Subscriber (multi-event)](#creating-a-subscriber-multi-event)
6. [Dispatching an Event](#dispatching-an-event)
7. [Registering Listeners Manually (runtime)](#registering-listeners-manually-runtime)
8. [Inspecting Registered Listeners](#inspecting-registered-listeners)
9. [Automatic Discovery and Caching](#automatic-discovery-and-caching)
10. [Events Provided by the Framework](#events-provided-by-the-framework)
11. [Profiler Integration](#profiler-integration)

---

## Module Files

| File | Role |
|---|---|
| `EventManager.php` | Central dispatcher, listener discovery and execution |
| `Listener/ListenerRegistration.php` | DTO representing a registered listener (class, priority, method, instance) |
| `Abstract/AbstractEvent.php` | Base class for every event |
| `Attribute/AsListener.php` | PHP 8 attribute for declaring a listener |
| `Interface/EventSubscriberInterface.php` | Interface for multi-event subscribers |
| `Event/RequestEvent.php` | Example event provided by the framework |

---

## Creating an Event

Every event must extend `AbstractEvent`, which implements `EventInterface` and handles propagation stopping.

```php
namespace App\Event;

use Neo\Core\Event\Abstract\AbstractEvent;

class UserRegisteredEvent extends AbstractEvent
{
    public function __construct(
        private readonly string $email,
        private readonly int $userId
    ) {}

    public function getEmail(): string  { return $this->email; }
    public function getUserId(): int    { return $this->userId; }
}
```

### Stopping propagation

```php
// Inside a listener, to prevent the next listeners (lower priority) from being called:
$event->stopPropagation();

// Check the state
if ($event->isPropagationStopped()) { /* ... */ }
```

---

## ListenerRegistration

**File:** `Listener/ListenerRegistration.php`

DTO that replaces the associative array `array{class, priority, method, instance}` formerly used internally by `EventManager`. Every registered listener — whether it comes from attribute scanning, a subscriber, or a manual addition — is represented by an instance of this class.

```php
final class ListenerRegistration implements \JsonSerializable
{
    public function __construct(
        private readonly string $class,             // Listener class
        private readonly int $priority,              // Priority (descending)
        private readonly string|array $method,        // Method name, or tuple {0: method, 1?: priority}
        private readonly ?object $instance = null,    // Direct instance, if registered via addListenerInstance()
    ) {}
}
```

Accessed via getters: `getClass()`, `getPriority()`, `getMethod()`, `getInstance()`, as well as `resolveMethodName()` which normalizes `method` (string or tuple) into a plain method name.

**Serialization:** the DTO implements `\JsonSerializable`, which lets `EventManager` write it as-is to the cache (`json_encode`). When reading the cache, `ListenerRegistration::fromArray()` rebuilds each instance from the decoded array — malformed entries (missing `class` key or wrong type, for example after a manual edit of the cache file) are ignored rather than causing a silent error further down the dispatch chain. Listeners registered by instance (`addListenerInstance`) are never written to the cache, since an arbitrary object cannot be reliably serialized.

---

## Creating a Listener with the `#[AsListener]` Attribute

The listener must be placed inside the `listenersPath` folder configured in the container. It is automatically discovered through a recursive scan at startup.

```php
namespace App\Listener;

use Neo\Core\Event\Attribute\AsListener;
use App\Event\UserRegisteredEvent;

#[AsListener(event: UserRegisteredEvent::class, priority: 10)]
class SendWelcomeEmailListener
{
    public function __construct(
        private readonly Mailer $mailer
    ) {}

    public function handle(UserRegisteredEvent $event): void
    {
        $this->mailer->send($event->getEmail(), 'Welcome!');
    }
}
```

**`#[AsListener]` parameters:**

| Parameter | Type | Description |
|---|---|---|
| `event` | `class-string` | FQCN of the listened event class |
| `priority` | `int` | Priority (higher = runs first) |

The default method called is `handle()`.

---

## Creating a Subscriber (multi-event)

A subscriber implements `EventSubscriberInterface` and declares a static `event → method` map.

```php
namespace App\Listener;

use Neo\Core\Event\Interface\EventSubscriberInterface;
use App\Event\UserRegisteredEvent;
use App\Event\UserLoggedInEvent;

class UserSubscriber implements EventSubscriberInterface
{
    public static function getSubscribedEvents(): array
    {
        return [
            UserRegisteredEvent::class => 'onRegister',
            UserLoggedInEvent::class   => 'onLogin',
        ];
    }

    public function onRegister(UserRegisteredEvent $event): void
    {
        // ...
    }

    public function onLogin(UserLoggedInEvent $event): void
    {
        // ...
    }
}
```

The value associated with each event can also be a `[method, priority]` tuple, e.g. `UserRegisteredEvent::class => ['onRegister', 10]`.

Subscribers are also automatically discovered during the scan if the class implements `EventSubscriberInterface`.

---

## Dispatching an Event

```php
use App\Event\UserRegisteredEvent;

$event = new UserRegisteredEvent('alice@example.com', 1);
$dispatcher = $container->get(EventManager::class);

$returnedEvent = $dispatcher->dispatch($event);
```

The dispatcher returns the event after execution, which allows retrieving data that listeners may have written to it.

---

## Registering Listeners Manually (runtime)

In addition to automatic discovery, listeners can be registered programmatically:

```php
// By class name (resolved by the container at runtime)
$dispatcher->addListener(
    eventClass: UserRegisteredEvent::class,
    listenerClass: SendWelcomeEmailListener::class,
    priority: 5,
    method: 'handle' // optional, default: 'handle'
);

// By direct instance
$dispatcher->addListenerInstance(
    eventClass: UserRegisteredEvent::class,
    instance: new AuditLogger(),
    method: 'handle',
    priority: 0
);

// By subscriber
$dispatcher->addSubscriber(new UserSubscriber());
```

Each of these calls internally creates a `ListenerRegistration` instance.

---

## Inspecting Registered Listeners

```php
// Every listener, grouped by event
$all = $dispatcher->getListeners();

// Listeners for a specific event
$list = $dispatcher->getListeners(UserRegisteredEvent::class);
// Returns a list of ListenerRegistration

foreach ($list as $registration) {
    $registration->getClass();
    $registration->getPriority();
    $registration->resolveMethodName();
}
```

---

## Automatic Discovery and Caching

On startup, `EventManager` recursively scans the `listenersPath` folder. For each `.php` file, it:

1. Extracts the FQCN (namespace + class name)
2. Looks for the `#[AsListener]` attribute on the class
3. Checks whether the class implements `EventSubscriberInterface`
4. Sorts listeners by descending priority

**In production** (`environment !== 'dev'`), the result (a list of `ListenerRegistration` serialized via `JsonSerializable`) is cached in:

```
storage/var/cache/events/listeners.php
```

This file is read directly on subsequent startups, with no re-scan: each decoded entry is rebuilt into a `ListenerRegistration` via `fromArray()`, and any malformed entry is silently ignored. In `dev` mode, the scan is always performed on every request.

---

## Events Provided by the Framework

### `RequestEvent`

Dispatched when an HTTP request is received. Contains the `Request` object.

```php
use Neo\Core\Event\Event\RequestEvent;
use Neo\Core\Event\Attribute\AsListener;

#[AsListener(event: RequestEvent::class, priority: 0)]
class RequestLoggerListener
{
    public function handle(RequestEvent $event): void
    {
        $request = $event->getRequest();
        error_log($request->getMethod() . ' ' . $request->getPath());
    }
}
```

### `ExceptionEvent`

Dispatched by `ErrorManager` when an exception is caught. Allows intercepting application errors (e.g. Slack notification, sending an email).

---

## Profiler Integration

If `NEO_PROFILER_ENABLED` is enabled, every call to `dispatch()` is timed and recorded in the Profiler's `events` collector, with:
- the event class name
- the list of listeners called
- the execution time in milliseconds