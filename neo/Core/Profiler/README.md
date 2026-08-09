# Profiler

The `Profiler` module is NeoPHP's development-time debugging toolbar and request inspector. It collects data throughout the request lifecycle (routing, database, views, events, middleware, cache, HTTP calls, validation...) via a collector-based architecture, displays it as an in-page toolbar, and exposes a full-page inspector reachable at `/_profiler/{token}`.

It is only ever active when `environment` is set to `dev` in `app.config.php`.

---

## Summary

1. [Overview](#overview)
2. [ProfilerModule](#profilermodule)
3. [ProfilerManager](#profilermanager)
4. [Collectors](#collectors)
   - [CollectorInterface](#collectorinterface)
   - [Awareness interfaces](#awareness-interfaces)
   - [Grouping panels (dropdowns)](#grouping-panels-dropdowns)
   - [Badges](#badges)
5. [Built-in collectors](#built-in-collectors)
6. [Rendering](#rendering)
   - [Toolbar](#toolbar)
   - [Full-page profiler](#full-page-profiler)
   - [Block types](#block-types)
7. [Timeline](#timeline)
8. [Boot Failures](#boot-failures)
9. [Security](#security)
10. [Storage & Cleanup](#storage--cleanup)
11. [Writing a custom collector](#writing-a-custom-collector)

---

## Overview

```
HTTP Request
     │
     ▼
ModuleManager::boot()          ← ProfilerModule scans & registers all *Collector.php classes
     │
     ▼
RouterManager::dispatch()      ← the matched controller runs, other managers instrument themselves
     │
     ▼
ProfilerResponseListener       ← saves the profile (JSON) + injects the toolbar into the HTML response
     │                            (or ErrorManager::injectProfilerToolbar() on a crash/error page)
     ▼
Browser
     │  click "Profiler" in the toolbar
     ▼
/_profiler/{token}             ← full-page inspector, reads the saved JSON profile
```

Two independent entry points render the exact same JSON profile:

- **`ProfilerPageController`** — normal route (`#[MainRoute(path: '/_profiler')]`), used when the app boots successfully.
- **`StandaloneProfilerRenderer`** — bypasses the whole application bootstrap (`ModuleManager`, DI resolution, routing). It is intercepted directly in `index.php` *before* `new App()`, so the profiler stays reachable even when a module crashes during boot and the app can never finish starting.

Both delegate the actual HTML construction to a single shared class, **`ProfilerHtmlRenderer`**, to avoid duplicating the sidebar/panel/block rendering logic.

---

## ProfilerModule

File: `ProfilerModule.php`

### Dependencies

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
        PackageModule::class,
    ];
}
```

### Activation

The profiler is only wired up when:

- `PHP_SAPI !== 'cli'`
- `environment === 'dev'` (read from `app.config.php`)

When both conditions are met, `NEO_PROFILER_ENABLED` is defined, all collectors are discovered and registered, and `ProfilerResponseListener` is subscribed to `ResponseEvent`.

### Collector discovery

`ProfilerModule::registerCollectors()` scans:

- the core directory (`Neo/Core`)
- every registered package's root path

for any file ending in `Collector.php` that implements `CollectorInterface`, is not abstract/an interface, and is not under a `\Tests\` or `\Fixture\` namespace segment. Each discovered collector is instantiated through the DI container and added to `ProfilerManager`, tagged with the package it belongs to (or `null` for core collectors), which is used later for grouping in the sidebar.

```php
public static function ensureCollectorsRegistered(Container $container, ProfilerManager $profiler): void
```

This static method is idempotent (guarded by `ProfilerManager::areCollectorsRegistered()`) and can be called from anywhere — notably from `ErrorManager` — to register collectors even if `ProfilerModule::init()` never got the chance to run because the application crashed earlier during boot. Each collector is resolved individually inside a `try/catch`, so a single collector whose dependencies aren't ready yet (e.g. a manager that depends on a module that crashed) is silently skipped rather than taking down every other collector.

---

## ProfilerManager

File: `ProfilerManager.php`

A per-request singleton (`ProfilerManager::getInstance()`) that:

- generates a unique **token** for the request (`bin2hex(random_bytes(6))`)
- holds the registered collectors and their originating package
- tracks total duration / peak memory
- optionally holds a **boot error** (see [Working even when the app fails to boot](#working-even-when-the-app-fails-to-boot))
- exports everything to a plain array via `export()`, later persisted as JSON

```php
public function export(?int $statusCode, string $method, string $path, string $ip): array
```

This array is what gets written to disk and later read back by both rendering entry points — collectors are never re-invoked when displaying `/_profiler/{token}`, only their previously-serialized `toolbarData()`/`profilerData()` output is read.

---

## Collectors

### CollectorInterface

File: `Interface/CollectorInterface.php`

```php
interface CollectorInterface
{
    public function getName(): string;

    /** @return array<string, mixed> */
    public function collect(): array;

    public function inToolbar(): bool;
    public function inProfiler(): bool;

    /** @return array{label: string, value: string, badge: string|null} */
    public function toolbarData(): array;

    /**
     * @return array{
     *     title: string,
     *     group?: string,
     *     badge: string|null,
     *     badgeType?: 'neutral'|'alert',
     *     metrics?: list<array{label: string, value: string, unit?: string}>,
     *     blocks: list<array<string, mixed>>
     * }
     */
    public function profilerData(): array;
}
```

`collect()` returns the raw data; `toolbarData()`/`profilerData()` shape it for display and are the only methods ever called by the renderer. **Every `return` statement inside `profilerData()` must repeat any `group`/`badgeType` keys** — a collector with multiple branches (e.g. "nothing happened" vs "N things happened") that only sets `group` on one branch will randomly appear outside its intended dropdown depending on which branch fires.

### Awareness interfaces

Some collectors need data that only exists at a specific point in the request lifecycle, after the collector itself has already been instantiated by the container. Rather than depending on a manager that might not be ready yet, these interfaces let `ProfilerResponseListener` push the data in once it's available:

```php
interface StatusAwareCollectorInterface
{
    public function setStatusCode(?int $statusCode): void;
}

interface ResponseAwareCollectorInterface
{
    public function setResponse(Response $response): void;
}
```

`RouteCollector` uses the former (to color its badge by HTTP status range); `ResponseCollector` uses the latter. Neither is populated on error pages rendered through `ErrorManager` — a `Response` object is never constructed on that path.

### Grouping panels (dropdowns)

Any collector can join a collapsible sidebar group by returning a `group` key from `profilerData()`:

```php
return [
    'title' => 'Queries',
    'group' => 'Database',
    // ...
];
```

Grouping is fully automatic — no central registry to maintain. The first collector to declare a given `group` string creates the dropdown; every subsequent collector with the same string joins it. Package-owned collectors are grouped separately and automatically under **Packages**, keyed by the package name (no `group` key needed for that).

### Badges

Two visual signals exist, both resolved centrally (never colored by the collector itself):

- **Toolbar badge** — `'badgeType' => 'alert'` renders red; anything else renders neutral gray.
- `RouteCollector` additionally sets `'badgeStatus' => true` so its toolbar badge is colored by the actual HTTP status range (2xx green, 3xx blue, 4xx orange, 5xx red) instead of the generic alert/neutral scheme.

Badges should only ever signal something worth the user's attention (an error, a blocked middleware, an invalid form) — never a plain count. `PackagesCollector` is the deliberate exception, showing the installed package count in neutral gray.

---

## Built-in collectors

| Collector | Group | Toolbar | What it shows |
|---|---|---|---|
| `RouteCollector` | — | ✅ | Matched route, controller/action, HTTP status |
| `AuthCollector` | — | ✅ | Core auth guard state, identifier, role, masked entity properties |
| `LoggerCollector` | — | ✅ | Logs written during the request, grouped by level (tabs) |
| `PackagesCollector` | *(special)* | — | Installed NeoPHP packages (name, version, description, source) |
| `ConfigurationCollector` | — | ✅ | Framework/PHP version, environment, php.ini settings, Composer packages |
| `BootErrorCollector` | — | ✅ (on crash only) | Exception that aborted module bootstrap, with stack trace |
| `RequestCollector` | Http | — | Method, headers, query, body, files (masked sensitive headers) |
| `ResponseCollector` | Http | — | Status, headers, content type/length (masked `Set-Cookie`) |
| `HttpClientCollector` ("Client") | Http | ✅ | Outgoing cURL requests: headers, body, response, timing |
| `SessionCollector` | Http | ✅ | Session attributes (masked sensitive keys) |
| `CookieCollector` | Http | ✅ | Request cookies (masked sensitive names) |
| `FlashCollector` | Http | ✅ | Pending flash messages, read non-destructively |
| `QueriesCollector` | Database | ✅ | Every SQL query, bindings, duration, connection |
| `FormsCollector` | Database | ✅ | Forms built via `FormFactory`, submission/validation state |
| `EventsCollector` | — | ✅ | Every dispatched event, listeners called, timing, propagation stop |
| `MiddlewareCollector` | — | ✅ | Middlewares run per route, pass/block, resolved params, rate-limit usage |
| `ViewCollector` | — | ✅ | Twig templates rendered, variable names passed, timing |
| `ValidatorCollector` | — | ✅ | Every constraint check: model, field, value, pass/fail |
| `AssetCollector` | — | ✅ | Resolved assets, cache hit vs recompiled, timing |
| `CacheCollector` | — | — | Cache operations (get/set/has/delete), hit/miss, TTL |
| `TimelineCollector` | — | — | Full request waterfall — see [Timeline](#timeline) |
| `AdminAuthCollector` *(NeoAdmin package)* | *(package)* | ✅ | Admin session state, resolved role, masked entity properties |

Collectors that log a running list (`Queries`, `Events`, `Middleware`, `Cache`, etc.) all rely on the same pattern: the underlying manager (`DatabaseManager`, `EventManager`, ...) keeps its own instrumentation log (usually a `static array` buffer, since some managers can be re-instantiated), and the collector simply reads it via `getLog()`/`getExecutionLog()`/`getRenders()` at render time.

---

## Rendering

### Toolbar

`Toolbar::render(?int $statusCode)` iterates every collector with `inToolbar() === true`, renders one chip per collector via `toolbar-item.template.php`, and resolves each badge's color centrally — never trusting a color choice made by the collector itself.

The toolbar HTML is injected into the response body (`</body>` insertion) by `ProfilerResponseListener`, or directly by `ErrorManager::injectProfilerToolbar()` on error pages.

### Full-page profiler

`ProfilerHtmlRenderer::render(array $data, string $token)` builds:

- the sidebar navigation (core collectors, grouped dropdowns, the Packages dropdown)
- one `<section class="panel">` per visible collector (`inProfiler() === true`)
- per-panel metrics (small stat cards) and blocks

The **Route** panel is the only one that additionally receives the request's global `Duration`/`Peak memory` metrics — every other panel only shows metrics it explicitly declares itself.

### Block types

A collector's `blocks` array can mix any of:

| Type | Use case |
|---|---|
| `kv` | Simple label/value pairs |
| `table` | Tabular data with a header row |
| `log-list` | Card-style entries with a collapsible "Show context" panel (used for logs, SQL queries, HTTP calls) |
| `tabs` | Nested sub-panels within one panel (e.g. Logger's per-level tabs, Forms' per-form tabs) |
| `timeline` | The waterfall chart — see below |

---

## Timeline

`TimelineRecorder` is a static buffer, independent from `ProfilerManager`, that any part of the framework can push timed events into:

```php
TimelineRecorder::record(string $category, string $label, float $startedAt, ?float $endedAt = null);
```

Offsets are computed relative to `NEO_START_TIME` (defined at the very top of `index.php`), so the timeline reflects the entire request lifecycle — module boot, routing, controller execution, and every instrumented subsystem (SQL, views, events, middleware, HTTP client, cache) — not just what happens after the profiler itself has booted.

`TimelineCollector` reads `TimelineRecorder::getEntries()` and renders them as a horizontal waterfall (`block-timeline.template.php`):

- bars are positioned at their true chronological offset and sized by true duration (fixed-pixel track, not percentage, so very short events remain visible)
- a **threshold** input hides events shorter than N ms
- a **category legend** doubles as a set of checkboxes to toggle whole categories on/off
- the chart supports **click-and-drag horizontal scrolling**

Instrumentation is always guarded with `class_exists(TimelineRecorder::class)`, so every manager that records into it keeps working identically (and for free) in production, where the Profiler namespace isn't even loaded.

---

## Boot Failures

If a module throws during `ModuleManager::boot()` (e.g. a missing required config key), the exception is caught, recorded via `ProfilerManager::setBootError()`, and re-thrown. `BootErrorCollector` then surfaces the exception class, message, file/line, and stack trace as its own panel — `inToolbar()`/`inProfiler()` both return `false` unless a boot error actually occurred, so it stays invisible in the normal case.

Because a crash during boot prevents `RouterManager::dispatch()` from ever running, `RouteCollector` reports `resolved: false` / "Not routed" instead of a misleading `n/a`.

`ErrorManager::injectProfilerToolbar()` renders the toolbar directly from `ProfilerManager::getInstance()` (a singleton, always available) rather than depending on the DI container having fully booted, and calls `ProfilerModule::ensureCollectorsRegistered()` defensively before rendering.

`/_profiler/{token}` itself is reachable via `StandaloneProfilerRenderer`, intercepted in `index.php` before `new App()` — so the inspector for a crashed request can still be opened, even though the app that produced it never finished booting.

---

## Security

`StandaloneProfilerRenderer` re-checks `environment === 'dev'` by reading `app.config.php` directly (without booting `ConfigModule`), so the standalone entry point can never leak profiles outside of dev — even though it deliberately bypasses the normal application bootstrap. Any other environment (or a failure to resolve the app's paths at all) results in a plain `404`, without revealing that a profiler endpoint exists.

The token accepted by the `/_profiler/{token}` route is restricted to hexadecimal characters via regex in `index.php`, preventing path traversal.

Sensitive values are masked where practical: request/response headers (`Authorization`, `Cookie`, `Set-Cookie`), session/cookie keys matching `password`/`secret`/`token`, and entity properties matching the same pattern in `AuthCollector`/`AdminAuthCollector`. This is **not** applied everywhere — notably, `ValidatorCollector` and outgoing HTTP client headers/bodies are logged unmasked, so treat the `var/cache/profiler/*.json` directory as sensitive.

---

## Storage & Cleanup

Profiles are persisted as JSON at:

```
storage/var/cache/profiler/{token}.json
```

`ProfilerCleaner::clean(string $storageDir)` runs deterministically after every save (both the normal flow and error pages), deleting profiles older than `MAX_AGE_SECONDS` and keeping at most `MAX_PROFILES` (newest first).

---

## Writing a custom collector

1. Create a class implementing `CollectorInterface`, named `*Collector.php`, anywhere under the core tree or a registered package's root — it will be picked up automatically on the next boot.
2. If it needs data only available later in the request (status code, response object), implement the relevant awareness interface instead of injecting a manager that might not be ready yet.
3. If it needs to log a running list across possibly-multiple instantiations of its underlying manager, use a `static` buffer on that manager (see `DatabaseManager::$queries`, `LoggerManager::$records`) rather than an instance property.
4. Set `group` in every branch of `profilerData()` if it should join a sidebar dropdown.
5. Only set a badge (`toolbarData()` or `profilerData()`) when there's something worth flagging — never a plain count.

```php
final class ExampleCollector implements CollectorInterface
{
    public function getName(): string { return 'example'; }

    public function collect(): array { return ['count' => 0]; }

    public function inToolbar(): bool { return true; }
    public function inProfiler(): bool { return true; }

    public function toolbarData(): array
    {
        return ['label' => 'Example', 'value' => '', 'badge' => null];
    }

    public function profilerData(): array
    {
        return [
            'title' => 'Example',
            'badge' => null,
            'blocks' => [
                ['type' => 'kv', 'section' => null, 'rows' => [
                    ['label' => 'Status', 'value' => 'Nothing to report.'],
                ]],
            ],
        ];
    }
}
```