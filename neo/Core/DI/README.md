# Dependency Injection

The `DI` (Dependency Injection) module is the heart of the NeoPHP framework. It provides an IoC (Inversion of Control) container compliant with the PSR-11 specification, capable of automatically resolving dependencies through reflection, managing singletons, interface bindings, tags, and calling callables with auto-wiring.

---

## Module Files

| File | Role |
|---|---|
| `Container.php` | Main IoC container (PSR-11) |
| `ContainerRegistry.php` | Global static access point to the container |
| `Exception/ContainerException.php` | Container-specific exception |

---

## Container

`Neo\Core\DI\Container` implements `Psr\Container\ContainerInterface`.

### Registering a service

#### `set(string $id, mixed $value)` — raw definition or factory

```php
// Scalar value or direct object
$container->set('app.name', 'NeoPHP');

// Factory callable: receives the container, executed only once (lazy singleton)
$container->set(MyService::class, fn(Container $c) => new MyService($c->get(Dep::class)));
```

#### `singleton(string $id, callable $factory)` — semantic alias for `set`

```php
$container->singleton(CacheService::class, fn(Container $c) => new CacheService());
```

#### `instance(string $id, object $object)` — register an already-built object

```php
$container->instance(LoggerInterface::class, $monologInstance);
```

#### `bind(string $abstract, string $concrete)` — bind an interface to an implementation

```php
$container->bind(LoggerInterface::class, FileLogger::class);
```

---

### Resolving a service

#### `get(string $id): mixed`

Resolves the service following this priority order:

1. Interface binding → follows the redirect to the concrete class
2. Already-resolved instance (internal cache)
3. Registered definition (factory or value)
4. Auto-wiring through reflection if the class exists

```php
$service = $container->get(MyService::class);
$name    = $container->get('app.name');
```

#### `has(string $id): bool`

Checks whether the service exists without resolving it.

```php
if ($container->has(CacheService::class)) {
    // ...
}
```

#### `make(string $id, array $parameters = []): object`

Always creates a **new instance** (ignores the cache), with the ability to pass additional named parameters.

```php
$request = $container->make(Request::class, ['method' => 'POST', 'path' => '/api']);
```

---

### Auto-wiring and resolution through reflection

The container uses `ReflectionClass` to inspect the constructor and automatically inject each parameter:

- Non-builtin class-typed parameter → recursive resolution via `get()`
- Nullable parameter that can't be found → `null`
- Parameter with a default value → uses the default value
- Otherwise → throws a `ContainerException`

```php
class OrderService
{
    public function __construct(
        private readonly PaymentGateway $gateway,
        private readonly Logger $logger,
        private readonly string $currency = 'EUR'
    ) {}
}

// The container automatically resolves PaymentGateway and Logger
$service = $container->get(OrderService::class);
```

#### Special case: `AbstractController`

Controllers extending `AbstractController` benefit from special handling: the container invokes the parent constructor separately (which receives the `Container`), then injects the child constructor's dependencies directly as properties, bypassing the normal constructor, to avoid conflicts with the `Container` parameter.

---

### Circular dependency detection

The container maintains a `$resolving` registry during resolution. If a class indirectly attempts to resolve itself, a `ContainerException` is thrown with the full chain:

```
Circular dependency detected while resolving 'A'. Chain: A → B → A
```

---

### Calling a callable with auto-wiring

#### `call(callable $callable, array $extraParams = []): mixed`

Calls any callable while injecting its dependencies:

```php
$result = $container->call(function (MyService $service, string $name = 'default') {
    return $service->process($name);
});

// Instance method
$result = $container->call([$controller, 'index']);

// Static method as a string
$result = $container->call('App\Controller\HomeController::index');
```

---

### Tags

Tags allow grouping several services under a common label and retrieving them as a list.

```php
// Register services with a tag
$container->set(XmlExporter::class, fn() => new XmlExporter());
$container->set(CsvExporter::class, fn() => new CsvExporter());

$container->tag(XmlExporter::class, 'exporter');
$container->tag(CsvExporter::class, 'exporter');

// Retrieve every tagged service
$exporters = $container->tagged('exporter');
// → [XmlExporter instance, CsvExporter instance]
```

---

### Inspecting the container

```php
$container->getDefinitions(); // List of IDs registered via set/singleton
$container->getInstances();   // List of already-resolved IDs (cache)
$container->getBindings();    // abstract → concrete array
```

---

## ContainerRegistry

`ContainerRegistry` is a static registry that allows accessing the container from anywhere in the code without explicit injection. It is initialized only once during bootstrap.

```php
use Neo\Core\DI\ContainerRegistry;

// During application bootstrap
ContainerRegistry::set($container);

// From anywhere
$container = ContainerRegistry::get();
$service   = ContainerRegistry::get()->get(MyService::class);
```

If `get()` is called before `set()`, a `ContainerException` is thrown:

```
Container Not Registered : Container has not been registered. Call ContainerRegistry::set() during bootstrap.
```

---

## ContainerException

`Neo\Core\DI\Exception\ContainerException` extends `FrameworkException`. It is thrown in the following cases:

| Code | Title | Cause |
|---|---|---|
| 404 | Service Not Found | Unknown container ID |
| 404 | Class Not Found | The class does not exist |
| 422 | Class Not Instantiable | Interface, abstract class, etc. |
| 422 | Parameter Cannot Be Resolved | Scalar parameter with no default value |
| 500 | Circular Dependency | Dependency loop detected |

---

## Complete resolution flow

```
get('MyService')
    ├── binding? → resolve the bound concrete class
    ├── cached instance? → return it directly
    ├── definition (factory)? → call the factory, cache it, return it
    ├── class_exists? → resolveClass() through reflection, cache it, return it
    └── otherwise → ContainerException (404)
```