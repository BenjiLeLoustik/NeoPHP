# Middleware

The `Middleware` submodule provides a declarative, attribute-based authorization pipeline for PHP 8, with built-in middlewares and support for custom middlewares.

---

## Table of Contents

1. [Structure](#structure)
2. [MiddlewareInterface](#middlewareinterface)
3. [MiddlewareMeta](#middlewaremeta)
4. [MiddlewareManager](#middlewaremanager)
5. [The `#[Middleware]` Attribute](#the-middleware-attribute)
6. [The `#[IsGranted]` Attribute](#the-isgranted-attribute)
7. [Built-in Middlewares](#built-in-middlewares)
8. [Creating a Custom Middleware](#creating-a-custom-middleware)
9. [CLI Command](#cli-command)

---

## Structure

```
Middleware/
├── MiddlewareManager.php               # Pipeline orchestrator
├── MiddlewareModule.php                # DI registration
├── Interface/
│   └── MiddlewareInterface.php         # Contract: handle(): bool
├── Meta/
│   └── MiddlewareMeta.php              # DTO for a resolved middleware's metadata
├── Attribute/
│   ├── Middleware.php                  # Declarative attribute (repeatable)
│   └── IsGranted.php                   # Shortcut for roles
├── Default/
│   ├── AuthMiddleware.php              # Login check
│   ├── GuestMiddleware.php             # Inverse of Auth
│   ├── IsGrantedMiddleware.php         # Role check (OR logic)
│   ├── RoleMiddleware.php              # Single role
│   ├── CsrfMiddleware.php              # CSRF token validation
│   ├── RateLimitMiddleware.php         # Limit by IP + path
│   ├── AuthRateLimitMiddleware.php     # Limit for login forms
│   └── ExampleMiddleware.php           # Starter template
├── Exception/
│   └── MiddlewareException.php         # 403 Forbidden
├── Extension/
│   └── MiddlewareControllerExtension.php # Injects getMiddleware()
└── Commands/
    └── MakeMiddlewareCommand.php       # CLI: make:middleware
```

---

## MiddlewareInterface

**File:** `Interface/MiddlewareInterface.php`

Every middleware must implement this interface:

```php
interface MiddlewareInterface
{
    /**
     * Returns true if the request can continue, false otherwise.
     */
    public function handle(): bool;
}
```

---

## MiddlewareMeta

**File:** `Meta/MiddlewareMeta.php`

DTO representing a middleware resolved for a given class/method, whether it comes from a `#[Middleware]`, `#[IsGranted]`, or `#[RateLimit]` attribute. Used internally by `MiddlewareManager::getMiddlewares()`: this is the typed form that replaces the old associative array and is iterated over by `run()` and `isAccessible()`.

```php
final class MiddlewareMeta
{
    public function __construct(
        public string $class,      // Middleware class to execute
        public string $message,    // Message on failure
        public string $onError,    // 'block' or 'soft'
        public ?string $redirect,  // Redirect route name, or null
        public bool $isClass,      // true if declared on the class, false if on the method
        public array $params,      // Parameters injected into the middleware's constructor
        public int $priority,      // Execution order (descending)
    ) {}
}
```

Each property is accessible via a dedicated getter (`getClass()`, `getMessage()`, `getOnError()`, `getRedirect()`, `isClass()`, `getParams()`, `getPriority()`). The DTO is purely descriptive: it has no setter, its instances are built once during middleware discovery and then cached.

---

## MiddlewareManager

**File:** `MiddlewareManager.php`

Orchestrator of the pipeline. Automatically called by the `RouterManager` before every controller invocation.

### Middleware Discovery

The manager reads the `#[Middleware]`, `#[IsGranted]`, and `#[RateLimit]` attributes on the controller's **class** AND on the targeted **method**, and turns them into `MiddlewareMeta` instances. Class-level middlewares are applied first, then method-level ones. The final order is determined by `getPriority()` (higher value = executed first).

### Execution

```php
$response = $middlewareManager->run($controllerClass, $methodName);

if ($response !== null) {
    // A middleware blocked the request
    $response->send();
    return;
}
// The request can continue normally
```

### Behavior on Failure

| `onError` | Behavior |
|-----------|--------------|
| `'block'` (default) | Throws a `MiddlewareException` (403) |
| `'soft'` | Adds a warning flash message, lets the request through |
| With `redirect` set | Redirects to the named route (302) with an optional flash message |

### Checking Without Executing

```php
// Checks whether a route is accessible without triggering side effects
$canAccess = $middlewareManager->isAccessible(MyController::class, 'edit');
```

### Error Inspection

```php
// Retrieve all error messages after a run()
$errors = $middlewareManager->getErrors();

// By specific middleware
$errors = $middlewareManager->getErrors(AuthMiddleware::class);

// Check whether there were any failures
if ($middlewareManager->hasError()) { /* ... */ }

// Execution results of a middleware (array of bool)
$results = $middlewareManager->getMiddleware(AuthMiddleware::class); // [true, false, ...]
```

### Maintenance Mode

Before executing the middlewares, `MiddlewareManager` checks for the `#[Maintenance]` attribute. If present (on the method or the class), it immediately returns a **503** response with the `maintenance.html.twig` view (if it exists).

---

## The `#[Middleware]` Attribute

**File:** `Attribute/Middleware.php`

Repeatable (`IS_REPEATABLE`), applicable to a class or a method.

```php
use Neo\Core\Security\Middleware\Attribute\Middleware;

// On a class: applied to all routes of the controller
#[Middleware(
    use: AuthMiddleware::class,
    message: 'You must be logged in.',
    onError: 'block',
    redirect: 'login',   // Route name for the redirect
    params: [],
    priority: 10         // Higher priority = executed first
)]
class DashboardController extends AbstractController { ... }

// On a method only
#[Middleware(use: CsrfMiddleware::class)]
#[Route('/settings', 'POST')]
public function update(): Response { ... }

// Multiple stacked middlewares
#[Middleware(use: AuthMiddleware::class, priority: 10)]
#[Middleware(use: CsrfMiddleware::class, priority: 5)]
class SecureController extends AbstractController { ... }
```

| Parameter | Type | Default | Description |
|-----------|------|--------|-------------|
| `use` | `class-string` | — | Middleware class to execute |
| `message` | `string` | `''` | Message on failure |
| `onError` | `string` | `'block'` | `'block'` or `'soft'` |
| `redirect` | `string\|null` | `null` | Route name for the redirect |
| `params` | `array` | `[]` | Parameters passed to the middleware's constructor |
| `priority` | `int` | `0` | Execution order (descending) |

---

## The `#[IsGranted]` Attribute

**File:** `Attribute/IsGranted.php`

Declarative shortcut for restricting access to certain roles. Automatically instantiates an `IsGrantedMiddleware`.

```php
use Neo\Core\Security\Middleware\Attribute\IsGranted;

// Access restricted to administrators
#[IsGranted(roles: ['admin'])]
class AdminController extends AbstractController { ... }

// Multiple allowed roles (OR logic)
#[IsGranted(
    roles: ['admin', 'moderator'],
    message: 'Access restricted to moderators.',
    onError: 'block',
    redirect: 'home'
)]
#[Route('/moderation', 'GET')]
public function moderation(): Response { ... }
```

| Parameter | Type | Default | Description |
|-----------|------|--------|-------------|
| `roles` | `array` | — | List of allowed roles (OR logic) |
| `message` | `string` | `''` | Message on failure |
| `onError` | `string` | `'block'` | Behavior on failure |
| `redirect` | `string\|null` | `null` | Redirect route |

---

## Built-in Middlewares

### AuthMiddleware

**File:** `Default/AuthMiddleware.php`

Checks that the user is logged in via the `AuthManager`.

```php
#[Middleware(use: AuthMiddleware::class, redirect: 'login')]
class DashboardController extends AbstractController { ... }
```

### GuestMiddleware

**File:** `Default/GuestMiddleware.php`

Inverse of `AuthMiddleware` — only allows users who are **not** logged in.

```php
#[Middleware(use: GuestMiddleware::class, redirect: 'dashboard')]
class LoginController extends AbstractController { ... }
```

### IsGrantedMiddleware

**File:** `Default/IsGrantedMiddleware.php`

Checks that the user has **at least one** of the required roles (OR logic). If no role is required, access is granted.

Prefer the `#[IsGranted]` attribute for declarative usage.

### RoleMiddleware

**File:** `Default/RoleMiddleware.php`

Checks a **single role**. Used with `params` in the `#[Middleware]` attribute.

```php
#[Middleware(
    use: RoleMiddleware::class,
    params: ['role' => 'editor'],
    message: 'Access restricted to editors.'
)]
public function edit(): Response { ... }
```

### CsrfMiddleware

**File:** `Default/CsrfMiddleware.php`

Validates the CSRF token for all unsafe requests (`POST`, `PUT`, `PATCH`, `DELETE`). `GET`, `HEAD`, `OPTIONS` methods are ignored.

```php
#[Middleware(use: CsrfMiddleware::class)]
#[Route('/settings', 'POST')]
public function update(): Response { ... }
```

### RateLimitMiddleware

**File:** `Default/RateLimitMiddleware.php`

Limits the number of requests per IP and per path. Uses the `CacheManager`. Throws a `FrameworkException` (429) when the limit is reached.

**Cache key:** `rate_limit:<md5(ip:path)>`, TTL equal to `decaySeconds`.

```php
// Via the RateLimit attribute (shortcut)
#[RateLimit(maxAttempts: 5, decaySeconds: 60)]
#[Route('/login', 'POST')]
public function login(): Response { ... }

// Via the Middleware attribute
#[Middleware(
    use: RateLimitMiddleware::class,
    params: ['maxAttempts' => 100, 'decaySeconds' => 3600],
    message: 'API quota exceeded.'
)]
class ApiController extends AbstractController { ... }
```

### AuthRateLimitMiddleware

**File:** `Default/AuthRateLimitMiddleware.php`

Specialized variant for login forms. Limits by **IP + identifier field value** (e.g. email) rather than by path.

```php
#[Middleware(
    use: AuthRateLimitMiddleware::class,
    params: ['maxAttempts' => 5, 'decaySeconds' => 300],
    message: 'Too many attempts. Try again in 5 minutes.'
)]
#[Route('/login', 'POST')]
public function login(): Response { ... }
```

---

## Creating a Custom Middleware

```php
<?php
declare(strict_types=1);

namespace App\Middlewares;

use Neo\Core\Security\Middleware\Interface\MiddlewareInterface;
use Neo\Core\Http\Request\Request;

class BusinessHoursMiddleware implements MiddlewareInterface
{
    public function __construct(
        private readonly Request $request
    ) {}

    public function handle(): bool
    {
        $hour = (int) date('H');
        return $hour >= 8 && $hour < 18;
    }
}
```

**Usage in a controller:**

```php
use Neo\Core\Security\Middleware\Attribute\Middleware;
use App\Middlewares\BusinessHoursMiddleware;

#[Middleware(
    use: BusinessHoursMiddleware::class,
    message: 'This service is only available between 8am and 6pm.',
    onError: 'block'
)]
#[Route('/support', 'GET')]
public function support(): Response { ... }
```

The `MiddlewareManager` instantiates the middleware via the DI container — all dependencies declared in the constructor are automatically injected.

---

## CLI Command

| Command | Description |
|----------|-------------|
| `make:middleware` | Generates a middleware skeleton in the project |

```bash
php bin/neo make:middleware MyMiddleware --project=Blog
# Generates: src/Blog/App/Middlewares/MyMiddleware.php

php bin/neo make:middleware AdminOnly --project=Blog --dir=Admin
# Generates: src/Blog/App/Middlewares/Admin/AdminOnlyMiddleware.php
```

The `Middleware` suffix is added automatically if absent. The `--force` option overwrites an existing file.