# Controller

The `Controller` module provides the base class for every web and API controller in a NeoPHP project. It relies on a dynamic extension system to inject methods and properties (such as `render()`, `redirectToRoute()`, `json()`, etc.) without rigid inheritance, through the `ExtensionManager` mechanism.

---

## Summary

- [AbstractController](#abstractcontroller)
- [ControllerExtensionInterface](#controllerextensioninterface)
- [Commands](#commands)
  - [make:controller](#makecontroller)

---

## AbstractController

**File:** `AbstractController.php`

Abstract base class that every controller in the project must extend. It exposes a dynamic delegation mechanism via `__call()` and `__get()` for methods and properties injected by extensions.

### How it works

At instantiation time, the `Container` is injected into the controller and `ExtensionManager::applyToController()` is called. Each registered extension can then call `registerMethod()` and `registerProperty()` to inject capabilities into the controller.

```php
public function __construct(?Container $container = null)
{
    if ($container === null) return;

    $this->container = $container;

    $container->get(ExtensionManager::class)->applyToController($this);
}
```

### Registering dynamic methods

```php
// Inside a ControllerExtension:
$controller->registerMethod('render', function(string $template, array $data = []) use ($twig) {
    return new Response($twig->render($template, $data));
});

$controller->registerMethod('redirectToRoute', function(string $name, array $params = []) use ($router) {
    return new RedirectResponse($router->generate($name, $params));
});
```

### Registering dynamic properties with caching

```php
// The property is resolved only once, then cached
$controller->registerProperty('session', fn() => $container->get(SessionManager::class));
```

### Access from controllers

Thanks to `__call()` and `__get()`, injected methods and properties can be called directly in child controllers:

```php
final class BlogController extends AbstractController
{
    #[Route(path: '/', name: 'index', methods: ['GET'])]
    public function index(): Response
    {
        // 'render' is a method injected by an extension
        return $this->render('pages/blog/index.html.twig', [
            'posts' => $this->postRepository->findAll(),
        ]);
    }

    #[Route(path: '/post/{id}', name: 'show', methods: ['GET'])]
    public function show(int $id): Response
    {
        $post = $this->postRepository->find($id);

        if (!$post) {
            // 'redirectToRoute' is also injected by an extension
            return $this->redirectToRoute('blog.index');
        }

        return $this->render('pages/blog/show.html.twig', ['post' => $post]);
    }
}
```

For an API controller, injected methods typically include `jsonSuccess()`, `jsonError()`, etc.:

```php
final class UserApiController extends AbstractController
{
    #[Route(path: '/', name: 'list', methods: ['GET'])]
    public function list(): JsonResponse
    {
        return $this->jsonSuccess(['users' => $this->userRepository->findAll()]);
    }
}
```

### Error handling

If an unregistered method or property is called, explicit exceptions are thrown:

```php
// __call() throws AbstractControllerException if the method is unknown:
// "Method 'unknownMethod' is not registered on this controller."

// __get() throws AbstractControllerException if the property is unknown:
// "Property 'unknownProp' is not registered on this controller."
```

### Internal structure

```php
abstract class AbstractController
{
    protected Container $container;

    /** @var array<string, Closure> */
    private array $methods = [];          // Methods injected by extensions

    /** @var array<string, Closure> */
    private array $propertyResolvers = []; // Property resolvers

    /** @var array<string, mixed> */
    private array $propertyCache = [];     // Cache of resolved properties
}
```

The property cache (`propertyCache`) guarantees that each property is resolved only once per controller lifecycle.

---

## ControllerExtensionInterface

**File:** `Interface/ControllerExtensionInterface.php`

Interface that every controller extension must implement.

```php
interface ControllerExtensionInterface
{
    public function extend(AbstractController $controller, Container $container): void;
}
```

### Creating a controller extension

An extension receives the controller instance and the dependency container. It uses `registerMethod()` and `registerProperty()` to enrich the controller.

```php
final class ViewControllerExtension implements ControllerExtensionInterface
{
    public function extend(AbstractController $controller, Container $container): void
    {
        $twig = $container->get(TwigEnvironment::class);

        $controller->registerMethod(
            'render',
            function(string $template, array $data = []) use ($twig): Response {
                return new Response($twig->render($template, $data));
            }
        );
    }
}
```

```php
final class SessionControllerExtension implements ControllerExtensionInterface
{
    public function extend(AbstractController $controller, Container $container): void
    {
        // Lazy property: resolved only if accessed
        $controller->registerProperty(
            'session',
            fn() => $container->get(SessionManager::class)
        );

        $controller->registerMethod(
            'getSession',
            fn(string $key, mixed $default = null) => $container->get(SessionManager::class)->get($key, $default)
        );

        $controller->registerMethod(
            'getFlash',
            fn(string $key) => $container->get(SessionManager::class)->getFlash($key)
        );
    }
}
```

Extensions are automatically discovered by the `ExtensionManager` when the controller is constructed.

---

## Commands

### `make:controller`

**File:** `Command/MakeControllerCommand.php`

Generates a controller (web or API) for a NeoPHP project, with pre-configured routing attributes.

#### Synopsis

```bash
php bin/neo make:controller [controller] --project=<Project> [--dir=<SubFolder>] [--api] [--force]
```

#### Arguments and options

| Name            | Type      | Description                                                          |
|-------------------|-----------|--------------------------------------------------------------------------|
| `controller`      | Argument  | Controller class name (optional, asked interactively)                    |
| `--project`       | Option    | Target project inside `./src/`                                            |
| `--dir` / `-d`    | Option    | Subfolder inside `App/Controllers/`                                       |
| `--api`           | Flag      | Generates an API controller (returns `JsonResponse`)                      |
| `--force`         | Flag      | Overwrites the file if it already exists                                   |

#### Name normalization

The controller name is automatically normalized:
- Converted to PascalCase
- The `Controller` suffix is appended if missing

Examples: `user` → `UserController`, `blog-post` → `BlogPostController`.

#### Generated web controller

```bash
php bin/neo make:controller Article --project=MyProject --dir=Blog
```

Generated file: `src/MyProject/App/Controllers/Blog/ArticleController.php`

```php
namespace Neo\Src\MyProject\App\Controllers\Blog;

use Neo\Core\Controller\AbstractController;
use Neo\Core\Routing\Attribute\MainRoute;
use Neo\Core\Routing\Attribute\Route;
use Neo\Core\Http\Response\Types\Response;

#[MainRoute(path: '/blog/article', name: 'blog.article')]
final class ArticleController extends AbstractController
{
    #[Route(path: '/', name: 'index', methods: ['GET'])]
    public function index(): Response
    {
        return $this->render('pages/blog/article/index.html.twig', []);
    }
}
```

#### Generated API controller

```bash
php bin/neo make:controller User --project=MyProject --api
```

Generated file: `src/MyProject/App/Controllers/UserController.php`

```php
namespace Neo\Src\MyProject\App\Controllers;

use Neo\Core\Controller\AbstractController;
use Neo\Core\Routing\Attribute\MainRoute;
use Neo\Core\Routing\Attribute\Route;
use Neo\Core\Http\Response\Types\JsonResponse;

#[MainRoute(path: '/user', name: 'user')]
final class UserController extends AbstractController
{
    #[Route(path: '/', name: 'index', methods: ['GET'])]
    public function index(): JsonResponse
    {
        return $this->jsonSuccess(['success' => true]);
    }
}
```

#### Automatic route building

The main route (`MainRoute`) is built from the subfolder and the controller name:

| Controller           | Folder   | `MainRoute` path    | `name`              |
|------------------------|----------|-----------------------|------------------------|
| `ArticleController`   | `Blog`   | `/blog/article`        | `blog.article`         |
| `UserController`      | none     | `/user`                 | `user`                  |
| `AdminController`     | `Panel`  | `/panel/admin`          | `panel.admin`           |

---

## Complete example: controller with access to helpers

```php
#[MainRoute(path: '/dashboard', name: 'dashboard')]
final class DashboardController extends AbstractController
{
    #[Route(path: '/', name: 'index', methods: ['GET'])]
    public function index(): Response
    {
        // 'render' injected by ViewControllerExtension
        return $this->render('pages/dashboard/index.html.twig', [
            'user' => $this->getSession('user'),
        ]);
    }

    #[Route(path: '/logout', name: 'logout', methods: ['POST'])]
    public function logout(): RedirectResponse
    {
        // 'session' property injected by SessionControllerExtension
        $this->session->destroy();

        // 'redirectToRoute' injected by ViewControllerExtension
        return $this->redirectToRoute('default.index');
    }

    #[Route(path: '/data', name: 'data', methods: ['GET'])]
    public function data(): JsonResponse
    {
        // 'jsonSuccess' injected by ApiControllerExtension
        return $this->jsonSuccess([
            'message' => 'ok',
            'flash'   => $this->getFlash('success'),
        ]);
    }
}
```

---

## File structure

```
neo/Core/Controller/
├── AbstractController.php              # Base class with dynamic delegation
├── Exception/
│   └── AbstractControllerException.php
├── Interface/
│   └── ControllerExtensionInterface.php  # Contract for extensions
└── Commands/
    └── MakeControllerCommand.php          # make:controller
```