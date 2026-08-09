# Debug

Symfony-style `dd()` / `dump()` helpers for NeoPHP. Renders any PHP value as a dark-themed, collapsible, syntax-highlighted tree — safe against circular references and oversized graphs (ORM entities, lazy relations) — and integrates with the Profiler so `dump()` calls survive even if the request fails afterward.

---

## Summary

1. [Module Structure](#module-structure)
2. [Global Helpers](#global-helpers)
    - [`dd()`](#dd)
    - [`dump()`](#dump)
    - [Disabled Outside `dev`](#disabled-outside-dev)
3. [Dumper](#dumper)
    - [Supported Types](#supported-types)
    - [Safety Limits](#safety-limits)
    - [Object Identity (`#id`)](#object-identity-id)
4. [DumpRecorder](#dumprecorder)
5. [DumpsCollector (Profiler)](#dumpscollector-profiler)
6. [Wiring](#wiring)

---

## Module Structure

```
Tools/Debug/
├── Dumper.php                   # Renders a value tree as HTML
├── DumpRecorder.php             # Static buffer of dump() calls for the current request
├── Helper/
│   └── Debug.php                # Global dd() / dump() / isDevEnvironment() functions
└── Collector/
    └── DumpsCollector.php       # Profiler integration
```

---

## Global Helpers

**File:** `Helper/Debug.php`

Two global functions, available anywhere in PHP code without dependency injection — same pattern as `translate()`.

### `dd()`

Dump-and-die. Renders one or more values **inline**, then halts execution immediately (`exit(1)`).

```php
dd($user);
dd($user, $request, $someArray);
```

Always echoes inline, even when the Profiler is active — since execution stops right away, the response is never fully sent and the toolbar would never actually be injected, so routing the output to the Profiler would mean losing it entirely.

### `dump()`

Non-halting dump. Does **not** print anything inline in the page. Instead, it records the rendered output into `DumpRecorder`, to be displayed later in the Profiler's **Dumps** tab.

```php
dump($data);
dump($user, $request);
```

This means a `dump()` call survives even if the controller throws an exception afterward, or if the template fails to render — the dump was already captured before either happens, and shows up in the Profiler regardless of how the request eventually resolves.

If `DumpRecorder` isn't available (Profiler namespace not loaded, e.g. in a context where it was never wired up), `dump()` falls back to echoing inline instead of silently discarding the output.

In CLI (`PHP_SAPI === 'cli'`), both functions fall back to plain `var_dump()`.

### Disabled Outside `dev`

Both functions are no-ops when `environment !== 'dev'` (read via `ConfigManager`). `dd()` does **not** halt execution in that case — a forgotten `dd()` in production code becomes silently inert rather than taking the site down.

```php
if (!function_exists('isDevEnvironment')) {
    function isDevEnvironment(): bool
    {
        try {
            return ContainerRegistry::get()
                ->get(ConfigManager::class)
                ->from('app')
                ->get('environment') === 'dev';
        } catch (\Throwable) {
            return false;
        }
    }
}
```

---

## Dumper

**File:** `Dumper.php`

Stateless-per-call renderer. `new Dumper()->render($vars)` returns a full standalone HTML fragment (including its own scoped `<style>` block), ready to `echo` or store.

### Supported Types

| Type | Rendering |
|---|---|
| `null` / `bool` / `int` / `float` | Colored inline token |
| `string` (≤ 200 chars) | Inline, quoted, with character count |
| `string` (> 200 chars) | Collapsible: 80-char preview in the summary, full content (truncated at 5000 chars) when expanded |
| `array` | Collapsible `array:N […]`, expanded one level deep by default |
| `object` | Collapsible `ClassName #id {…}`, properties prefixed with visibility (`-` private, `#` protected, `+` public); uninitialized typed properties show `uninitialized` instead of throwing |
| `\UnitEnum` / `\BackedEnum` | `EnumClass::CASE` (backed cases also show `= 'value'`) |
| `\DateTimeInterface` | Custom rendering showing `date:` and `timezone:` instead of empty braces |

Every collapsible node shows opening/closing braces (`{`/`}` or `[`/`]`) that stay aligned regardless of nesting depth, and expand automatically at the top level (`open` on the first `<details>`, collapsed below).

### Safety Limits

Two independent guards prevent an ORM entity graph (relations, lazy proxies) from exhausting memory:

- **`MAX_DEPTH = 15`** — stops recursing past 15 levels of nesting, regardless of object identity.
- **`MAX_NODES = 2000`** — a global counter across the *entire* render call. Once exceeded, every further value renders as `*OUTPUT TRUNCATED — TOO MANY VALUES*` instead of continuing to descend. This catches cases `MAX_DEPTH` alone can't: lazy-loaded relations that generate a fresh object on every access (never repeating the same `spl_object_id`), producing an effectively unbounded — but not strictly circular — graph.

### Object Identity (`#id`)

Every dumped object shows its `spl_object_id()` next to the class name:

```
#307 AdminUser {
    +role => #45 AdminRole {
        -id => 1
        -name => "ROLE_ADMIN" (10)
    }
}
```

If the *same* object instance is encountered again anywhere in the tree (a genuine circular reference, not just another instance of the same class), it renders collapsed with a reference back to the id already shown, instead of recursing infinitely or printing an opaque `*RECURSION*`:

```
#45 AdminRole #45 {…}
```

---

## DumpRecorder

**File:** `DumpRecorder.php`

A static, per-request buffer. `dump()` pushes each call's rendered HTML plus its caller (`file:line`) into it; nothing reads from it except `DumpsCollector`.

```php
final class DumpRecorder
{
    public static function record(string $html, ?string $caller): void;

    /** @return list<array{html: string, caller: string|null}> */
    public static function getDumps(): array;
}
```

---

## DumpsCollector (Profiler)

**File:** `Collector/DumpsCollector.php`

Discovered automatically by the Profiler's collector scan (any `*Collector.php` implementing `CollectorInterface`). Not shown in the toolbar (`inToolbar(): false`) — only in the full-page Profiler, under its own **Dumps** panel.

Each recorded `dump()` call becomes its own tab, labeled with a short `file.php:line` caller reference, so multiple dumps in one request stay easy to browse instead of stacking into one long scroll:

```php
private function shortLabel(?string $caller, int $index): string
{
    $lastColon = strrpos($caller, ':');
    $path = substr($caller, 0, $lastColon);
    $line = substr($caller, $lastColon + 1);

    return '#' . ($index + 1) . ' ' . basename($path) . ':' . $line;
}
```

The dump's HTML is injected as-is via a `raw-html` profiler block type (not escaped, unlike `kv`/`table` blocks — the content is Dumper's own trusted output, not user data).

---

## Wiring

`dd()`/`dump()` are declared without a class or namespace, so they aren't autoloaded by PSR-4. They're loaded via an explicit `require_once` early in the request lifecycle (in `index.php`, alongside the `translate()` helper):

```php
require_once __DIR__ . '/vendor/autoload.php';
require_once __DIR__ . '/neo/Core/Translation/Helper/Translate.php';
require_once __DIR__ . '/neo/Core/Tools/Debug/Helper/Debug.php';
```

`DumpsCollector` requires no separate wiring — it's picked up automatically by `ProfilerModule`'s file scan like any other collector, as long as `Tools/Debug/Collector/DumpsCollector.php` sits under a scanned path (core, package, or `appPath`).