# Scanner

The `Scanner` submodule provides two complementary reflection tools:
- `ScannerAttributeManager`, for discovering and reading PHP 8 attributes on a class, its methods, its properties, and its methods' parameters.
- `ScannerFileManager`, for discovering PHP classes across one or more directories, without duplicating directory-walking logic in every consumer (routing, events, extensions, console commands, cron jobs, modules).

---

## Summary

1. [Structure](#structure)
2. [ScannerAttributeManager](#scannerattributemanager)
3. [Scan Configuration](#scan-configuration)
4. [AttributeScanResult](#attributescanresult)
5. [ScannerFileManager](#scannerfilemanager)
6. [FileScanResult](#filescanresult)
7. [Use Cases](#use-cases)

---

## Structure

```
Scanner/
├── ScannerAttributeManager.php         # Reflection tool for PHP attributes
├── ScannerFileManager.php              # Directory-based class discovery tool
├── ScannerModule.php                   # DI registration
├── Result/
│   ├── AttributeScanResult.php         # DTO representing an attribute scan result entry
│   └── FileScanResult.php              # DTO representing a file scan result entry
└── Extension/
    └── ScannerControllerExtension.php  # Injects getScanner() / getFileScanner() into controllers
```

---

## ScannerAttributeManager

**File:** `ScannerAttributeManager.php`

```php
use Neo\Core\Utils\Scanner\ScannerAttributeManager;

$scanner = new ScannerAttributeManager(MyController::class);

$results = $scanner
    ->onClass()           // Scan the class itself
    ->onMethods()         // Scan the methods
    ->onProperties()      // Scan the properties
    ->onParameters()      // Scan the methods' parameters
    ->withAttribute(Route::class) // Filter by a specific attribute
    ->scan();
```

`scan()` returns a `list<AttributeScanResult>`.

---

## Scan Configuration

### Scope

| Method | Target |
|---------|-------|
| `onClass()` | The class itself |
| `onMethods(?int $filter)` | Methods (filter `ReflectionMethod::IS_PUBLIC`, etc.) |
| `onProperties(?int $filter)` | Properties |
| `onParameters()` | Methods' parameters |
| `onAll()` | Everything (class + methods + properties + parameters) |

### Attribute Filter

```php
// Filter by a specific attribute
->withAttribute(Route::class)

// Scan all attributes with no filter
->withAllAttributes()
```

### Examples

```php
// Public methods only
$results = (new ScannerAttributeManager(MyController::class))
    ->onMethods(ReflectionMethod::IS_PUBLIC)
    ->withAttribute(Route::class)
    ->scan();

// Private and protected properties
$results = (new ScannerAttributeManager(MyService::class))
    ->onProperties(ReflectionProperty::IS_PRIVATE | ReflectionProperty::IS_PROTECTED)
    ->withAttribute(Inject::class)
    ->scan();

// Everything with no attribute filter
$results = (new ScannerAttributeManager(MyClass::class))
    ->onAll()
    ->withAllAttributes()
    ->scan();
```

---

## AttributeScanResult

**File:** `Result/AttributeScanResult.php`

DTO that replaces the associative array `array{target, attribute, arguments, type, reflection}` formerly returned by `scan()`. Each result entry is now an instance of this class, exposed via getters:

```php
class AttributeScanResult
{
    public function __construct(
        private string $target,       // Human-readable label, e.g. 'MyController::index()'
        private object $attribute,    // Instance of the attribute
        private array $arguments,     // Raw arguments of the attribute's constructor
        private string $type,         // 'class'|'method'|'property'|'parameter'
        private ReflectionClass|ReflectionMethod|ReflectionProperty|ReflectionParameter $reflection,
    ) {}
}
```

Access via `getTarget()`, `getAttribute()`, `getArguments()`, `getType()`, `getReflection()`. The type returned by `getReflection()` depends on `getType()`: a consumer that needs a `ReflectionMethod` must check `instanceof ReflectionMethod` before using it (since the class name is `class`, `method`, `property`, or `parameter`, the associated reflection type varies accordingly).

```php
foreach ($results as $entry) {
    $route = $entry->getAttribute();       // instance of the attribute, e.g. Route
    $reflection = $entry->getReflection(); // ReflectionMethod, ReflectionClass, ...

    if ($reflection instanceof ReflectionMethod) {
        echo $reflection->getName();
    }
}
```

---

## ScannerFileManager

**File:** `ScannerFileManager.php`

Discovers PHP classes across one or more directories: walks each path recursively, extracts the namespace and class name of every matching file, requires it, and returns the resolved FQCN alongside its file path — without needing PSR-4 to already know about the class.

This is the tool behind controller discovery (`RouterManager`), listener discovery (`EventManager`), extension discovery (`ExtensionManager`), console command discovery (`ConsoleManager`), cron job discovery (`CronScanner`), and module discovery (`ModuleManager`). Each of these managers still owns its own attribute filtering (via `ScannerAttributeManager`) — `ScannerFileManager` only replaces the directory-walking and FQCN-resolution part that used to be duplicated in every one of them.

```php
use Neo\Core\Utils\Scanner\ScannerFileManager;

$results = new ScannerFileManager()
    ->paths(['/path/to/App/Controllers'])
    ->scan();

foreach ($results as $result) {
    $result->getFqcn();     // e.g. 'App\Controllers\HomeController'
    $result->getFilePath(); // absolute path to the file
}
```

`scan()` returns a `list<FileScanResult>`.

### Builder Options

| Method | Effect |
|---------|-------|
| `paths(list<string> $paths)` | One or more directories to scan (merged into a single result list) |
| `withExtension(string $extension)` | File extension to match (default `php`) |
| `withPathSegment(string $segment)` | Only keep files whose path contains this directory segment, e.g. `'Commands'` matches `.../App/Commands/Foo.php` |
| `withFilenameSuffix(string $suffix)` | Only keep files whose filename ends with this suffix, e.g. `'Extension.php'` |

### Examples

```php
// Scan a project's controllers directory
$results = (new ScannerFileManager())
    ->paths([$container->get('controllersPath')])
    ->scan();

// Scan multiple directories at once (e.g. project + framework core)
$results = (new ScannerFileManager())
    ->paths([$basePath . '/neo', $basePath . '/src'])
    ->withFilenameSuffix('Extension.php')
    ->scan();

// Only keep files under a 'Commands' directory segment
$results = (new ScannerFileManager())
    ->paths($commandBasePaths)
    ->withPathSegment('Commands')
    ->scan();
```

Each result is typically fed into `ScannerAttributeManager` for further filtering:

```php
foreach ($results as $result) {
    if (!class_exists($result->getFqcn())) {
        continue;
    }

    $attributes = new ScannerAttributeManager($result->getFqcn())
        ->onClass()
        ->withAttribute(Command::class)
        ->scan();

    // ...
}
```

---

## FileScanResult

**File:** `Result/FileScanResult.php`

Immutable DTO returned by `ScannerFileManager::scan()`:

```php
final class FileScanResult
{
    public function __construct(
        private string $fqcn,
        private string $filePath,
    ) {}
}
```

Access via `getFqcn()` and `getFilePath()`.

---

## Use Cases

### Route Discovery

```php
$scanner = new ScannerAttributeManager(HomeController::class);
$routes  = $scanner
    ->onMethods(ReflectionMethod::IS_PUBLIC)
    ->withAttribute(Route::class)
    ->scan();

foreach ($routes as $entry) {
    /** @var Route $route */
    $route  = $entry->getAttribute();
    $method = $entry->getReflection(); // ReflectionMethod

    if (!$method instanceof ReflectionMethod) {
        continue;
    }

    echo sprintf('%s %s → %s::%s',
        $route->method,
        $route->path,
        HomeController::class,
        $method->getName()
    );
}
```

### Dependency Injection via Attribute

```php
$scanner = new ScannerAttributeManager(MyService::class);
$injects = $scanner
    ->onProperties(ReflectionProperty::IS_PRIVATE | ReflectionProperty::IS_PROTECTED)
    ->withAttribute(Inject::class)
    ->scan();

foreach ($injects as $entry) {
    $prop = $entry->getReflection();

    if (!$prop instanceof ReflectionProperty) {
        continue;
    }

    /** @var Inject $inject */
    $inject  = $entry->getAttribute();
    $service = $container->get($inject->type);
    $prop->setValue($instance, $service);
}
```

### Controller/Command/Listener Discovery Across Directories

```php
$results = (new ScannerFileManager())
    ->paths([$projectControllersPath])
    ->scan();

foreach ($results as $result) {
    if (!class_exists($result->getFqcn())) {
        continue;
    }

    // ... further attribute-based filtering via ScannerAttributeManager
}
```

### Performance

For frequent scans (e.g. route discovery at startup), it is recommended to cache the results via `CacheManager` (`array` or `files` driver) in order to avoid the cost of reflection on every request. `RouterManager` and `EventManager` already cache their discovery results this way; `ScannerFileManager` itself performs no caching, it is a stateless discovery primitive.