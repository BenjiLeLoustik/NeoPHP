# Module

The `Module` subsystem is the entry point for every feature of the NeoPHP framework. It defines a uniform contract (`ModuleInterface`) that every component must follow, and provides a manager (`ModuleManager`) capable of automatically discovering, ordering, and initializing every module present in the project.

---

## Summary

1. [Core Concepts](#core-concepts)
2. [ModuleInterface](#moduleinterface)
3. [ModuleManager](#modulemanager)
4. [ModuleException](#moduleexception)
5. [Creating a Custom Module](#creating-a-custom-module)
6. [Module Lifecycle](#module-lifecycle)
7. [Dependency Resolution](#dependency-resolution)

---

## Core Concepts

A **module** in NeoPHP is a class whose name ends with `Module.php` and which implements `ModuleInterface`. The framework recursively scans a base directory looking for these classes, loads them in the correct order based on their dependencies, then initializes them in the dependency injection container.

Modules play three roles:

- **Discovery**: `ModuleManager` automatically finds every module through PHP reflection.
- **Registration**: each module declares its bindings in the DI container via `register()`.
- **Initialization**: each module returns a "manager" object via `init()`, which then becomes available in the container under the `<name>.manager` alias.

---

## ModuleInterface

File: `Interface/ModuleInterface.php`

```php
namespace Neo\Core\Module\Interface;

use Neo\Core\DI\Container;

interface ModuleInterface
{
    /**
     * Returns the module classes this module depends on.
     * @return array<class-string>
     */
    public function dependencies(): array;

    /**
     * Registers bindings in the DI container (called before init).
     */
    public function register(Container $container): void;

    /**
     * Initializes the module and returns its main object (manager, service...).
     */
    public function init(Container $container): object;
}
```

### Contract

| Method | Role | When it's called |
|---|---|---|
| `dependencies()` | Declares the required modules | Before `register()` and `init()` |
| `register()` | Binds classes to the DI container | Before `init()` |
| `init()` | Initializes and returns the manager | After `register()` has run for every ordered module |

---

## ModuleManager

File: `ModuleManager.php`

### Automatic Discovery: `discover()`

```php
$manager = new ModuleManager($container);
$manager->discover('/path/to/neo/Core');
```

The `discover()` method recursively walks the provided directory. It only selects files whose name ends with `Module.php`, extracts the FQCN (namespace + class) through regular expressions on the source code, checks that the class does implement `ModuleInterface`, and by default excludes classes located in `\Tests\` or `\Fixture\` namespaces.

```php
// Exclude test fixtures (default behavior)
$manager->discover($basePath, excludeTestFixtures: true);

// Include everything (useful for tests)
$manager->discover($basePath, excludeTestFixtures: false);
```

### Startup: `boot()`

```php
$manager->boot();
```

The `boot()` method:

1. Computes the topological order of modules via `resolveDependencyOrder()`.
2. For each module (in order):
   - Instantiates the module class.
   - Calls `register($container)`.
   - Injects the results of dependent modules as sub-keys in the container (e.g. `router.configModule`).
   - Calls `init($container)` and retrieves the result.
   - Registers the result in the container under `<alias>.manager` (e.g. `router.manager`).

### Alias Derivation

`ModuleManager` automatically derives a short alias from the class name. The `Module` suffix is removed and the first letter is lowercased:

| Class | Alias |
|---|---|
| `RouterModule` | `router` |
| `ProfilerModule` | `profiler` |
| `ConfigModule` | `config` |

---

## ModuleException

File: `Exception/ModuleException.php`

```php
namespace Neo\Core\Module\Exception;

use Neo\Core\Error\Exception\FrameworkException;

class ModuleException extends FrameworkException {}
```

`ModuleException` extends `FrameworkException` and is thrown in two situations:

**Circular dependency:**
```
Circular dependency detected in module "App\RouterModule".
HTTP code: 500
Context: ['module' => '...', 'chain' => [...]]
```

**Module not found:**
```
Module 'App\MissingModule' is missing but is required by 'App\RouterModule'.
Make sure it is present in neo/Core and correctly loaded.
HTTP code: 500
Context: ['missing' => '...', 'requiredBy' => '...']
```

---

## Creating a Custom Module

Here is a complete example of a module that registers a `PaymentService` service in the container:

```php
<?php
declare(strict_types=1);

namespace App\Payment;

use Neo\Core\DI\Container;
use Neo\Core\Module\Interface\ModuleInterface;
use Neo\Core\Utils\Config\ConfigModule;

class PaymentModule implements ModuleInterface
{
    public function dependencies(): array
    {
        // This module requires ConfigModule to be initialized before it
        return [
            ConfigModule::class,
        ];
    }

    public function register(Container $container): void
    {
        // Lazy registration: the factory is only called on the first get()
        $container->set(PaymentService::class, fn(Container $c) => new PaymentService(
            $c->get('payment.configModule')->from('payment')->all()
        ));
    }

    public function init(Container $container): object
    {
        // Return the module's main object
        // It will be accessible via $container->get('payment.manager')
        return $container->get(PaymentService::class);
    }
}
```

### Naming Convention

- The file must be named `PaymentModule.php`.
- The class must implement `ModuleInterface`.
- The name must end with `Module`.

---

## Module Lifecycle

```
discover()
    ├── Recursive scan of the directory
    ├── Filter: name ends with "Module.php"
    ├── FQCN extraction via regex
    ├── Check: implements ModuleInterface
    └── Added to $this->modules[]

boot()
    ├── resolveDependencyOrder() → topological sort
    └── For each module (in order):
        ├── new $moduleClass()
        ├── $module->register($container)
        ├── Injection of dependency results
        │     e.g. $container->set('router.configModule', $configResult)
        ├── $result = $module->init($container)
        └── $container->set('router.manager', $result)
```

---

## Dependency Resolution

`ModuleManager` uses a topological sort algorithm (DFS - Depth-First Search) to determine the initialization order. It automatically detects circular dependencies.

**Example with nested dependencies:**

```
ProfilerModule
    ├── ResponseModule
    ├── EventModule
    ├── RouterModule
    │     ├── ConfigModule   ← shared
    │     └── ViewModule
    ├── AuthModule
    ├── TranslationModule
    └── ConfigModule         ← already resolved, skipped
```

Resulting initialization order:
1. `ConfigModule`
2. `ViewModule`
3. `RouterModule`
4. `ResponseModule`
5. `EventModule`
6. `AuthModule`
7. `TranslationModule`
8. `ProfilerModule`

Each module is initialized only once, even if it appears in multiple dependency trees.