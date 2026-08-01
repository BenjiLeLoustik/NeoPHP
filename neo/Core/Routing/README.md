# Routing

The `Routing` module is responsible for matching incoming HTTP requests to PHP controller methods. It relies on **PHP 8 attributes** to declare routes directly on classes and methods, manages a cache system for production, and exposes helpers in controllers and Twig views.

---

## Summary

1. [Overview](#overview)
2. [RouterModule](#routermodule)
3. [Route Attributes](#route-attributes)
   - [MainRoute](#mainroute)
   - [Route](#route)
   - [RateLimit](#ratelimit)
   - [Maintenance](#maintenance)
4. [RouterManager](#routermanager)
5. [RouteCollection](#routecollection)
6. [Extensions](#extensions)
   - [RouterControllerExtension](#routercontrollerextension)
   - [RouterViewExtension](#routerviewextension)
7. [The debug:router Command](#the-debugrouter-command)
8. [Error Handling](#error-handling)

---

## Overview

```
HTTP Request
     │
     ▼
RouterManager::dispatch()
     ├── Controller scan (or reads the JSON cache in prod)
     ├── Path matching via compilePattern()
     ├── MiddlewareManager::run()   ← middleware checks
     └── Parameter resolution + controller invocation
```

---

## RouterModule

File: `RouterModule.php`

### Dependencies

```php
public function dependencies(): array
{
    return [
        ConfigModule::class,
        ViewModule::class,
    ];
}
```

### Registration

The module registers `RouterManager` in the DI container:

```php
public function register(Container $container): void
{
    $container->set(RouterManager::class, fn(Container $c) => new RouterManager($c));
}
```

In CLI mode, `init()` returns the module itself (the router isn't useful in console). In HTTP mode, it returns the `RouterManager` instance, which scans the controllers at construction time.

---

## Route Attributes

### MainRoute

File: `Attribute/MainRoute.php`

`#[MainRoute]` applies to a controller **class** and defines a path and name prefix for every route in the class.

```php
use Neo\Core\Routing\Attribute\MainRoute;
use Neo\Core\Routing\Attribute\Route;

#[MainRoute(path: '/admin', name: 'admin')]
class AdminController extends AbstractController
{
    // Final route: GET /admin/dashboard
    // Final name : admin.dashboard
    #[Route(path: '/dashboard', name: 'dashboard')]
    public function dashboard(): Response { ... }

    // Final route: DELETE /admin/users/{id}
    // Final name : admin.delete_user
    #[Route(path: '/users/{id}', name: 'delete_user', methods: ['DELETE'])]
    public function deleteUser(int $id): Response { ... }
}
```

| Parameter | Type | Description |
|---|---|---|
| `path` | `string` | Path prefix (the trailing `/` is automatically removed) |
| `name` | `string` | Name prefix (a `.` is added as a separator) |

### Route

File: `Attribute/Route.php`

`#[Route]` applies to a **public** controller method.

```php
// Simple route
#[Route(path: '/articles', name: 'article.list')]
public function list(): Response { ... }

// Route with multiple HTTP methods
#[Route(path: '/articles', name: 'article.create', methods: ['POST'])]
public function create(): Response { ... }

// Route with a dynamic parameter and a regex constraint
#[Route(
    path: '/articles/{slug}',
    name: 'article.show',
    methods: ['GET'],
    requirements: ['slug' => '[a-z0-9\-]+']
)]
public function show(string $slug): Response { ... }

// Route with an optional parameter
#[Route(path: '/archive/{year?}', name: 'archive')]
public function archive(?int $year = null): Response { ... }
```

| Parameter | Type | Default | Description |
|---|---|---|---|
| `path` | `string` | — | Route path (`{param}` or `{param?}` segments) |
| `name` | `string` | `''` | Unique route name |
| `methods` | `array` | `['GET']` | Accepted HTTP methods |
| `requirements` | `array` | `[]` | Regex constraints per parameter |

### RateLimit

File: `Attribute/RateLimit.php`

`#[RateLimit]` can be placed on a **class** or a **method**. It limits the number of requests per IP over a time window.

```php
use Neo\Core\Routing\Attribute\RateLimit;

// Limits the whole class to 30 requests per minute
#[RateLimit(maxAttempts: 30, decaySeconds: 60)]
class ApiController extends AbstractController
{
    // Specific limit for this action: 5 attempts per minute
    #[RateLimit(maxAttempts: 5, decaySeconds: 60, message: 'Too many login attempts.')]
    #[Route(path: '/login', name: 'api.login', methods: ['POST'])]
    public function login(): Response { ... }
}
```

| Parameter | Type | Default | Description |
|---|---|---|---|
| `maxAttempts` | `int` | `60` | Maximum number of requests |
| `decaySeconds` | `int` | `60` | Window duration in seconds |
| `message` | `string` | `'Too many requests...'` | Error message returned (429) |

### Maintenance

File: `Attribute/Maintenance.php`

`#[Maintenance]` can be placed on a **class** (the whole controller) or a **method** (a specific route). When the route is hit, `MiddlewareManager` returns a 503 response.

```php
use Neo\Core\Routing\Attribute\Maintenance;

// The whole controller is under maintenance
#[Maintenance(message: 'Update in progress, please check back in a few minutes.')]
class ShopController extends AbstractController { ... }

// Only one action is under maintenance
#[Maintenance]
#[Route(path: '/checkout', name: 'shop.checkout', methods: ['POST'])]
public function checkout(): Response { ... }
```

If the `maintenance.html.twig` view file exists, it is rendered with the `message` variable. Otherwise, the plain text message is returned directly.

---

## RouterManager

File: `RouterManager.php`

### Controller Scan

On startup, `RouterManager` recursively walks the `controllersPath` directory (registered in the DI container). It extracts the FQCN of each PHP file, uses `ScannerAttributeManager` to read the `#[MainRoute]` (on the class) and `#[Route]` (on public methods) attributes, then populates the `RouteCollection`.

### Production Cache

In `prod` mode (environment != 'dev'), routes are cached in a JSON file:

```
storage/var/cache/router/routes.json
```

On the next startup, this file is read directly and the PHP scan is skipped. The cache is invalidated manually (by deleting the file) or during a deployment.

In `dev` mode, the scan is performed on every request and route conflicts trigger an `E_USER_WARNING`.

### Dispatching a Request

```php
$response = $routerManager->dispatch($request, $response);
```

Dispatch algorithm:

1. Normalizes the HTTP method and the path.
2. For each registered route: attempts matching via `compilePattern()`.
3. If the path matches but not the HTTP method: 405 exception.
4. If no route matches: 404 exception.
5. If a route matches: runs the middlewares, then injects the parameters into the controller method.

### Injecting Parameters into the Controller

`RouterManager` uses reflection to inject parameters into controller methods:

1. **Route parameter** (e.g. `$id`): injected from the pattern's captures.
2. **Non-primitive type** (e.g. `Request $request`): resolved from the DI container.
3. **Default value**: used if the parameter has a defined default value.

```php
#[Route(path: '/users/{id}', name: 'user.show')]
public function show(int $id, Request $request): Response
{
    // $id is injected from the URL
    // $request is resolved from the DI container
}
```

### Pattern Compilation

Dynamic segments are compiled into regular expressions with named captures:

| Segment | Generated regex | Optional |
|---|---|---|
| `{id}` | `/(?P<id>[^/]+)` | No |
| `{slug}` with `requirements: ['slug' => '[a-z0-9\-]+']` | `/(?P<slug>[a-z0-9\-]+)` | No |
| `{year?}` | `(?:/(?P<year>[^/]+))?` | Yes |

### URL Generation

```php
// From anywhere with access to RouterManager
$url = $routerManager->generateUrl('article.show', ['slug' => 'my-article']);
// Result: '/articles/my-article'

// Optional parameters not provided → segment removed
$url = $routerManager->generateUrl('archive'); // '/archive'
```

---

## RouteCollection

File: `Collection/RouteCollection.php`

`RouteCollection` is the router's internal data structure. It organizes routes by HTTP method, then by path.

```php
// Internal structure
[
    'GET' => [
        '/articles'      => ['name' => 'article.list',  'controller' => '...', 'action' => 'list',  'requirements' => []],
        '/articles/{id}' => ['name' => 'article.show',  'controller' => '...', 'action' => 'show',  'requirements' => ['id' => '\d+']],
    ],
    'POST' => [
        '/articles' => ['name' => 'article.create', 'controller' => '...', 'action' => 'create', 'requirements' => []],
    ],
]
```

### Serialization (cache)

```php
// Serialization to JSON (prod)
$json = json_encode($collection->toArray());

// Deserialization from JSON
$collection = RouteCollection::fromArray(json_decode($json, true));
```

---

## Extensions

### RouterControllerExtension

File: `Extension/RouterControllerExtension.php`

This extension is automatically applied to every controller that extends `AbstractController`. It adds the following methods:

```php
// Get the path of a named route
$path = $this->getRoutePath('article.show', ['slug' => 'my-article']);
// Result: '/articles/my-article'

// Get the return URL (referrer or fallback)
$back = $this->getRedirectBack('home');
$back = $this->getRedirectBack(null); // fallback to '/'

// Redirects
return $this->redirectToRoute('dashboard');
return $this->redirectToRoute('article.show', ['slug' => 'test']);
return $this->redirectToPath('/absolute/path', 301);
return $this->redirectBack('home', [], 302);
```

### RouterViewExtension

File: `Extension/RouterViewExtension.php`

This extension adds two global functions in Twig templates:

```twig
{# Generate a link from a route name #}
<a href="{{ path('article.show', {slug: 'my-article'}) }}">Read the article</a>
<a href="{{ path('home') }}">Home</a>

{# Get the name of the current route (useful for active menus) #}
{% if currentRoute() == 'admin.dashboard' %}
    <li class="active">Dashboard</li>
{% endif %}
```

---

## The debug:router Command

File: `Commands/DebugRouterCommand.php`

The `debug:router` command displays every route registered for a project, colorized by HTTP method.

```bash
# Display every route for the "app" project
php neo debug:router --project=app

# Filter by HTTP method
php neo debug:router --project=app --method=POST

# Filter by route name
php neo debug:router --project=app --name=admin

# Filter by path
php neo debug:router --project=app --path=/api
```

Example output:

```
Routes for app (12)

  GET     /                                  home                    App\Controller\HomeController::index
  GET     /admin/dashboard                   admin.dashboard         App\Controller\AdminController::dashboard
  GET     /articles                          article.list            App\Controller\ArticleController::list
  POST    /articles                          article.create          App\Controller\ArticleController::create
  GET     /articles/{slug}                   article.show            App\Controller\ArticleController::show
  DELETE  /admin/users/{id}                  admin.delete_user       App\Controller\AdminController::deleteUser
```

Colors: `GET` green, `POST` yellow, `PUT`/`PATCH` cyan, `DELETE` red.

---

## Error Handling

| Situation | Exception | HTTP Code |
|---|---|---|
| No route matches | `RouteNotFoundException` | 404 |
| Known path, wrong HTTP method | `RouterException` | 405 |
| Non-injectable controller parameter | `RouterException` | 500 |
| Error inside the controller | `RouterException` (wrapping) | 500 |

`RouteNotFoundException` and `RouterException` extend `FrameworkException` and are handled by the framework's global error handler.