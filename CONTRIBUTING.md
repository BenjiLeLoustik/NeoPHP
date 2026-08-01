# CONTRIBUTING — Creating a module in NeoPHP

This guide explains how to add a new subsystem to the framework core (`neo/Core/`).
It covers a module's lifecycle, extending the base controller, adding Twig functions, writing tests, and validating before opening a PR.

## Table of contents

- [Prerequisites](#prerequisites)
- [The module system](#the-module-system)
- [Creating a module](#creating-a-module)
- [Adding methods to AbstractController](#adding-methods-to-abstractcontroller)
- [Adding Twig functions and filters](#adding-twig-functions-and-filters)
- [Writing tests](#writing-tests)
- [Validating before a PR](#validating-before-a-pr)

---

## Prerequisites

- PHP >= 8.5
- Composer installed
- Up-to-date dependencies: `composer install`

---

## The module system

### How discovery works

At startup, `Neo\App` creates a `ModuleManager` and calls `discover(__DIR__ . '/Core')`.

The `ModuleManager` recursively scans `neo/Core/` and loads any file whose name ends in `Module.php`. It keeps the classes that:

- implement `ModuleInterface`
- are not abstract
- do not belong to a `Tests` or `Fixture` namespace

It then resolves the boot order based on the dependencies declared by each module, and calls in order:

1. `register(Container $container)` on **all** modules
2. `boot(Container $container)` on **all** modules (in dependency order)

### `ModuleInterface` contract

```php
// neo/Core/Module/Interface/ModuleInterface.php

interface ModuleInterface
{
    /** @return array<class-string> */
    public function dependencies(): array;

    public function register(Container $container): void;

    public function boot(Container $container): void;
}
```

| Method | When called | Role |
|---------|---------------|------|
| `dependencies()` | Before any boot | Declare the modules this module depends on |
| `register()` | Phase 1 | Register services in the DI container |
| `boot()` | Phase 2 | Initialize services, wire up extensions |

### `AbstractModule` base class

Extend `AbstractModule` rather than implementing `ModuleInterface` directly:

- stores the container in `$this->container`
- exposes `$this->get(string $class)` as a shortcut to `$container->get()`
- provides a `resolveDependencies()` hook called during `boot()`
- implements `dependencies()` by default → `[]`

```php
// neo/Core/Module/Abstract/AbstractModule.php (excerpt)

class AbstractModule implements ModuleInterface
{
    protected Container $container;

    public function dependencies(): array
    {
        return [];
    }

    public function register(Container $container): void {}

    public function boot(Container $container): void
    {
        $this->container = $container;
        $this->resolveDependencies();
    }

    protected function get(string $abstract): mixed
    {
        return $this->container->get($abstract);
    }

    protected function resolveDependencies(): void {}
}
```

---

## Creating a module

### 1. Choose the location

Each subsystem has its own folder under `neo/Core/`. Create:

```
neo/Core/
└── MySubsystem/
    ├── MySubsystemModule.php   ← required file, auto-detected
    ├── MyService.php
    └── Exception/
        └── MySubsystemException.php
```

### 2. Name the module file

The file name **must** end in `Module.php`. This is the only auto-discovery criterion.

### 3. Write the module

```php
<?php
declare(strict_types=1);

namespace Neo\Core\MySubsystem;

use Neo\Core\DI\Container;
use Neo\Core\Module\Abstract\AbstractModule;
use Neo\Core\Utils\Config\ConfigModule; // example dependency

final class MySubsystemModule extends AbstractModule
{
    /**
     * Declare the modules this module needs.
     * The ModuleManager guarantees they are booted before this one.
     *
     * @return array<class-string>
     */
    public function dependencies(): array
    {
        return [
            ConfigModule::class,
            // ViewModule::class, if adding a Twig extension
        ];
    }

    /**
     * Register services in the container.
     * Always use factory closures (lazy initialization).
     */
    public function register(Container $container): void
    {
        $container->set(MyService::class, fn(Container $c) => new MyService($c));

        // If this module exposes a Twig extension:
        // $container->set(MyViewExtension::class, fn(Container $c) => new MyViewExtension(
        //     $c->get(MyService::class)
        // ));
        // $container->tag(MyViewExtension::class, 'twig.extension');
    }

    /**
     * Initialize critical services after all modules have registered.
     * Only call explicitly if eager initialization is needed.
     */
    protected function resolveDependencies(): void
    {
        $this->get(MyService::class);
    }
}
```

### 4. Write the service

```php
<?php
declare(strict_types=1);

namespace Neo\Core\MySubsystem;

use Neo\Core\DI\Container;

final class MyService
{
    public function __construct(private readonly Container $container)
    {
    }

    public function doSomething(): string
    {
        return 'result';
    }
}
```

### Important rules

- Always `declare(strict_types=1)` at the top of the file.
- Services registered in `register()` must be **factory closures**: `fn(Container $c) => new MyService(...)`.
- Never resolve a service in `register()` — only in `boot()` / `resolveDependencies()`.
- If your module depends on another one, declare it in `dependencies()` and never assume a load order.

---

## Adding methods to AbstractController

When a module needs to expose shortcuts in application controllers (e.g. `$this->myService()`), create a `*ControllerExtension.php` file.

### How discovery works

`AbstractController` recursively scans `neo/Core/` when it is instantiated and automatically loads any file ending in `ControllerExtension.php` that implements `ControllerExtensionInterface`.

### Contract

```php
// neo/Core/Controller/Interface/ControllerExtensionInterface.php

interface ControllerExtensionInterface
{
    public function extend(AbstractController $controller, Container $container): void;
}
```

### Creating a controller extension

```php
<?php
declare(strict_types=1);

namespace Neo\Core\MySubsystem;

use Neo\Core\Controller\AbstractController;
use Neo\Core\Controller\Interface\ControllerExtensionInterface;
use Neo\Core\DI\Container;

final class MySubsystemControllerExtension implements ControllerExtensionInterface
{
    public function extend(AbstractController $controller, Container $container): void
    {
        // Register a method callable from the controller
        $controller->registerMethod(
            'getMyService',
            fn(): MyService => $container->get(MyService::class)
        );

        // Register a property (lazy, cached after first access)
        $controller->registerProperty(
            'myService',
            fn(): MyService => $container->get(MyService::class)
        );
    }
}
```

### Usage in an application controller

```php
final class PostController extends AbstractController
{
    #[Route(path: '/', name: 'index', methods: ['GET'])]
    public function index(): Response
    {
        // Via registered method
        $service = $this->getMyService();

        // Via registered property (equivalent, with caching)
        $result = $this->myService->doSomething();

        return $this->render('pages/index.html.twig', ['result' => $result]);
    }
}
```

### API available in `extend()`

| Call | Behavior |
|-------|-------------|
| `$controller->registerMethod('name', fn() => ...)` | Adds a method callable via `$this->name()` |
| `$controller->registerProperty('name', fn() => ...)` | Adds a lazy property via `$this->name` (cached) |

---

## Adding Twig functions and filters

When a module needs to expose helpers in Twig templates, create a `*ViewExtension.php` file.

### How registration works

`ViewModule` retrieves every service tagged `'twig.extension'` in the container and passes them to `ViewManager::addExtension()` at boot time. So all you need to do is:

1. Implement `TwigExtensionInterface`
2. Register the service in the module with `$container->tag($class, 'twig.extension')`

### Contract

```php
// neo/Core/View/Interface/TwigExtensionInterface.php

interface TwigExtensionInterface
{
    /** @return array<string, mixed> */
    public function getFunctions(): array;

    /** @return array<string, mixed> */
    public function getFilters(): array;
}
```

### Creating a Twig extension

```php
<?php
declare(strict_types=1);

namespace Neo\Core\MySubsystem;

use Neo\Core\View\Interface\TwigExtensionInterface;

final class MySubsystemViewExtension implements TwigExtensionInterface
{
    public function __construct(private readonly MyService $service)
    {
    }

    /**
     * @return array<string, array{callable: \Closure, options: array<string, mixed>}>
     */
    public function getFunctions(): array
    {
        return [
            // Twig name => ['callable' => closure, 'options' => []]
            'my_helper' => [
                'callable' => fn(string $value) => $this->service->doSomething(),
                'options'  => [],
            ],
            'my_other_helper' => [
                'callable' => fn(int $n) => $n * 2,
                'options'  => [],
            ],
        ];
    }

    /**
     * @return array<string, array{callable: \Closure, options: array<string, mixed>}>
     */
    public function getFilters(): array
    {
        return [
            // Usable as a filter: {{ value|my_filter }}
            'my_filter' => [
                'callable' => fn(string $v) => strtoupper($v),
                'options'  => [],
            ],
        ];
    }
}
```

### Wiring the extension into the module

Add to `MySubsystemModule`:

```php
public function dependencies(): array
{
    return [
        ConfigModule::class,
        ViewModule::class, // ← required if exposing a Twig extension
    ];
}

public function register(Container $container): void
{
    $container->set(MyService::class, fn(Container $c) => new MyService($c));

    $container->set(
        MySubsystemViewExtension::class,
        fn(Container $c) => new MySubsystemViewExtension($c->get(MyService::class))
    );

    // Required tag so ViewModule discovers the extension
    $container->tag(MySubsystemViewExtension::class, 'twig.extension');
}
```

### Usage in a Twig template

```twig
{# Function #}
{{ my_helper('value') }}
{{ my_other_helper(4) }}

{# Filter #}
{{ 'text'|my_filter }}
```

---

## Writing tests

Each module must have a `Tests/` folder with a `phpunit.xml` and at least one `*Test.php` file.

### Expected structure

```
neo/Core/MySubsystem/
├── MySubsystemModule.php
├── MyService.php
└── Tests/
    ├── phpunit.xml
    ├── MyServiceTest.php
    └── Fixture/           ← support classes used only in tests
        └── ...
```

### phpunit.xml

Copy this template, adapting the suite name (`name`):

```xml
<?xml version="1.0" encoding="UTF-8"?>
<phpunit xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
         xsi:noNamespaceSchemaLocation="../../../../vendor/phpunit/phpunit/phpunit.xsd"
         bootstrap="../../../../vendor/autoload.php"
         colors="true"
         cacheDirectory=".phpunit.cache"
         failOnWarning="true"
         failOnDeprecation="true"
         displayDetailsOnTestsThatTriggerDeprecations="true"
         displayDetailsOnTestsThatTriggerWarnings="true">

    <testsuites>
        <testsuite name="MySubsystem">
            <directory suffix="Test.php">.</directory>
        </testsuite>
    </testsuites>

    <source>
        <include>
            <directory suffix=".php">..</directory>
        </include>
        <exclude>
            <directory>.</directory>
        </exclude>
    </source>
</phpunit>
```

> **Note on paths**: the path to `phpunit.xsd` and `autoload.php` goes up 4 levels (`../../../../`) because the file lives in `neo/Core/<Subsystem>/Tests/`. If your `Tests/` folder is nested deeper (e.g. `neo/Core/Security/Auth/Tests/`), adjust the number of `../`.

### Writing a test

```php
<?php
declare(strict_types=1);

namespace Neo\Core\MySubsystem\Tests;

use Neo\Core\DI\Container;
use Neo\Core\MySubsystem\MyService;
use Neo\Core\MySubsystem\MySubsystemModule;
use PHPUnit\Framework\TestCase;

final class MyServiceTest extends TestCase
{
    private MyService $service;

    protected function setUp(): void
    {
        $container = new Container();
        $module = new MySubsystemModule();
        $module->register($container);

        $this->service = $container->get(MyService::class);
    }

    public function testDoSomethingReturnsExpectedResult(): void
    {
        self::assertSame('result', $this->service->doSomething());
    }
}
```

### Testing integration with the ModuleManager

When the module has dependencies or modifies global state, test its full lifecycle:

```php
public function testModuleRegistersService(): void
{
    $container = new Container();
    $manager = new ModuleManager($container);
    $manager->discover(__DIR__ . '/../..'); // points to neo/Core/MySubsystem
    $manager->boot();

    self::assertTrue($container->has(MyService::class));
}
```

### Testing a controller extension

```php
public function testControllerExtensionRegistersMethod(): void
{
    $container = new Container();
    $container->set(MyService::class, fn() => new MyService($container));

    // AbstractController automatically instantiates extensions in its constructor
    $controller = new class($container) extends AbstractController {};

    // Verify that the method is accessible
    self::assertInstanceOf(MyService::class, $controller->getMyService());
}
```

### Testing a Twig extension

```php
public function testViewExtensionExposesFunctions(): void
{
    $service = new MyService(new Container());
    $ext = new MySubsystemViewExtension($service);

    self::assertArrayHasKey('my_helper', $ext->getFunctions());
    self::assertArrayHasKey('my_filter', $ext->getFilters());
}
```

### Test fixtures

Classes used **only** in tests (mocks, stubs, error scenarios) must live in `Tests/Fixture/`. The `ModuleManager` automatically excludes them from discovery thanks to the filter on `\Tests\` and `\Fixture\` namespaces.

---

## Validating before a PR

The `runner_dev.sh` script at the project root is the single entry point for validating a module before opening a PR. It runs, in sequence:

1. **PHPUnit tests** — discovery of every `phpunit.xml` under `neo/Core/*/Tests/`
2. **PHPStan static analysis** — level 6 on `neo/`
3. **Summary** — `Yes / No` verdict on whether to open the PR

### Running validation

```bash
bash runner_dev.sh
```

### Conditions for opening a PR

The script displays **"Yes, you can open the PR"** and returns exit code 0 only if:

- at least one test suite is discovered (`phpunit.xml` present)
- all suites pass with no error or warning
- PHPStan runs with no error

In every other case, the script returns exit code 1 and details the cause of failure.

### PHPStan configuration

The `phpstan.neon` file at the project root analyzes `neo/` at **level 6**:

```
Level 6: checks return types, parameter types,
           uninitialized properties, missing methods, etc.
```

Ignored identifiers (already configured in `phpstan.neon`):

| Identifier | Reason |
|-------------|--------|
| `constant.notFound` | Dynamically defined constants |
| `property.protected` | Controller extension pattern |
| `method.notFound` | Methods registered dynamically via `registerMethod()` |
| `constructor.unusedParameter` | Certain attribute constructors |
| `new.static` | Static inheritance |
| `nullCoalesce.variable` | Optional variables |
| `attribute.abstract` | Attributes on abstract classes |

If PHPStan reports a legitimate error, fix it. Do not add an `ignoreErrors` entry without documented justification.

### Pre-PR checklist

```
[ ] The module folder is under neo/Core/<MySubsystem>/
[ ] The module file ends in Module.php
[ ] The module extends AbstractModule or implements ModuleInterface
[ ] Dependencies are declared in dependencies()
[ ] Services are registered via factory closures in register()
[ ] If a Twig extension: ViewModule::class is in dependencies() and the 'twig.extension' tag is set
[ ] If a controller extension: the file ends in ControllerExtension.php
[ ] A Tests/ folder exists with phpunit.xml and at least one *Test.php
[ ] bash runner_dev.sh returns exit code 0
```