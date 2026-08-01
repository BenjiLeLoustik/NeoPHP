# Scanner

The `Scanner` submodule provides a reflection tool for discovering and reading PHP 8 attributes on a class, its methods, its properties, and its methods' parameters.

---

## Summary

1. [Structure](#structure)
2. [ScannerAttributeManager](#scannerattributemanager)
3. [Scan Configuration](#scan-configuration)
4. [AttributeScanResult](#attributescanresult)
5. [Use Cases](#use-cases)

---

## Structure

```
Scanner/
├── ScannerAttributeManager.php         # Reflection tool for PHP attributes
├── AttributeScanResult.php             # DTO representing a scan result entry
├── ScannerModule.php                   # DI registration
└── Extension/
    └── ScannerControllerExtension.php  # Injects getScanner() into controllers
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

**File:** `AttributeScanResult.php`

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

### Performance

For frequent scans (e.g. route discovery at startup), it is recommended to cache the results via `CacheManager` (`array` or `files` driver) in order to avoid the cost of reflection on every request.