# Controller

Le module `Controller` fournit la classe de base pour tous les contrôleurs web et API d'un projet NeoPHP. Il s'appuie sur un système d'extensions dynamiques pour injecter des méthodes et propriétés (comme `render()`, `redirectToRoute()`, `json()`, etc.) sans héritage rigide, via le mécanisme d'`ExtensionManager`.

---

## Sommaire

- [AbstractController](#abstractcontroller)
- [ControllerExtensionInterface](#controllerextensioninterface)
- [Commandes](#commandes)
  - [make:controller](#makecontroller)

---

## AbstractController

**Fichier :** `AbstractController.php`

Classe de base abstraite que tout contrôleur du projet doit étendre. Elle expose un mécanisme de délégation dynamique via `__call()` et `__get()` pour les méthodes et propriétés injectées par les extensions.

### Principe de fonctionnement

Au moment de l'instanciation, le `Container` est injecté dans le contrôleur et `ExtensionManager::applyToController()` est appelé. Chaque extension enregistrée peut alors appeler `registerMethod()` et `registerProperty()` pour injecter des capacités dans le contrôleur.

```php
public function __construct(?Container $container = null)
{
    if ($container === null) return;

    $this->container = $container;

    $container->get(ExtensionManager::class)->applyToController($this);
}
```

### Enregistrement de méthodes dynamiques

```php
// Dans une ControllerExtension :
$controller->registerMethod('render', function(string $template, array $data = []) use ($twig) {
    return new Response($twig->render($template, $data));
});

$controller->registerMethod('redirectToRoute', function(string $name, array $params = []) use ($router) {
    return new RedirectResponse($router->generate($name, $params));
});
```

### Enregistrement de propriétés dynamiques avec cache

```php
// La propriété est résolue une seule fois puis mise en cache
$controller->registerProperty('session', fn() => $container->get(SessionManager::class));
```

### Accès dans les contrôleurs

Grâce à `__call()` et `__get()`, les méthodes et propriétés injectées sont appelables directement dans les contrôleurs enfants :

```php
final class BlogController extends AbstractController
{
    #[Route(path: '/', name: 'index', methods: ['GET'])]
    public function index(): Response
    {
        // 'render' est une méthode injectée par une extension
        return $this->render('pages/blog/index.html.twig', [
            'posts' => $this->postRepository->findAll(),
        ]);
    }

    #[Route(path: '/post/{id}', name: 'show', methods: ['GET'])]
    public function show(int $id): Response
    {
        $post = $this->postRepository->find($id);

        if (!$post) {
            // 'redirectToRoute' est aussi injectée par extension
            return $this->redirectToRoute('blog.index');
        }

        return $this->render('pages/blog/show.html.twig', ['post' => $post]);
    }
}
```

Pour un contrôleur API, les méthodes injectées incluent généralement `jsonSuccess()`, `jsonError()`, etc. :

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

### Gestion des erreurs

Si une méthode ou propriété non enregistrée est appelée, des exceptions explicites sont levées :

```php
// __call() lève AbstractControllerException si la méthode est inconnue :
// "Method 'unknownMethod' is not registered on this controller."

// __get() lève AbstractControllerException si la propriété est inconnue :
// "Property 'unknownProp' is not registered on this controller."
```

### Structure interne

```php
abstract class AbstractController
{
    protected Container $container;

    /** @var array<string, Closure> */
    private array $methods = [];          // Méthodes injectées par les extensions

    /** @var array<string, Closure> */
    private array $propertyResolvers = []; // Résolveurs de propriétés

    /** @var array<string, mixed> */
    private array $propertyCache = [];     // Cache des propriétés résolues
}
```

Le cache des propriétés (`propertyCache`) garantit que chaque propriété n'est résolue qu'une seule fois par cycle de vie du contrôleur.

---

## ControllerExtensionInterface

**Fichier :** `Interface/ControllerExtensionInterface.php`

Interface que doit implémenter toute extension de contrôleur.

```php
interface ControllerExtensionInterface
{
    public function extend(AbstractController $controller, Container $container): void;
}
```

### Créer une extension de contrôleur

Une extension reçoit l'instance du contrôleur et le conteneur de dépendances. Elle utilise `registerMethod()` et `registerProperty()` pour enrichir le contrôleur.

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
        // Propriété lazy : résolue seulement si accédée
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

Les extensions sont découvertes automatiquement via l'`ExtensionManager` lors de la construction du contrôleur.

---

## Commandes

### `make:controller`

**Fichier :** `Commands/MakeControllerCommand.php`

Génère un contrôleur (web ou API) pour un projet NeoPHP, avec les attributs de routage pré-configurés.

#### Synopsis

```bash
php bin/neo make:controller [controller] --project=<Project> [--dir=<SubFolder>] [--api] [--force]
```

#### Arguments et options

| Nom           | Type      | Description                                                        |
|---------------|-----------|--------------------------------------------------------------------|
| `controller`  | Argument  | Nom de la classe du contrôleur (optionnel, demandé interactivement)|
| `--project`   | Option    | Projet cible dans `./src/`                                         |
| `--dir` / `-d`| Option    | Sous-dossier dans `App/Controllers/`                               |
| `--api`       | Flag      | Génère un contrôleur API (retourne `JsonResponse`)                 |
| `--force`     | Flag      | Écrase le fichier si il existe déjà                               |

#### Normalisation du nom

Le nom du contrôleur est normalisé automatiquement :
- Conversion en PascalCase
- Ajout du suffixe `Controller` si absent

Exemples : `user` → `UserController`, `blog-post` → `BlogPostController`.

#### Contrôleur web généré

```bash
php bin/neo make:controller Article --project=MyProject --dir=Blog
```

Fichier généré : `src/MyProject/App/Controllers/Blog/ArticleController.php`

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

#### Contrôleur API généré

```bash
php bin/neo make:controller User --project=MyProject --api
```

Fichier généré : `src/MyProject/App/Controllers/UserController.php`

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

#### Construction automatique des routes

La route principale (`MainRoute`) est construite à partir du sous-dossier et du nom du contrôleur :

| Contrôleur          | Dossier  | `MainRoute` path    | `name`              |
|---------------------|----------|---------------------|---------------------|
| `ArticleController` | `Blog`   | `/blog/article`     | `blog.article`      |
| `UserController`    | aucun    | `/user`             | `user`              |
| `AdminController`   | `Panel`  | `/panel/admin`      | `panel.admin`       |

---

## Exemple complet : contrôleur avec accès aux helpers

```php
#[MainRoute(path: '/dashboard', name: 'dashboard')]
final class DashboardController extends AbstractController
{
    #[Route(path: '/', name: 'index', methods: ['GET'])]
    public function index(): Response
    {
        // 'render' injecté par ViewControllerExtension
        return $this->render('pages/dashboard/index.html.twig', [
            'user' => $this->getSession('user'),
        ]);
    }

    #[Route(path: '/logout', name: 'logout', methods: ['POST'])]
    public function logout(): RedirectResponse
    {
        // 'session' propriété injectée par SessionControllerExtension
        $this->session->destroy();

        // 'redirectToRoute' injecté par ViewControllerExtension
        return $this->redirectToRoute('default.index');
    }

    #[Route(path: '/data', name: 'data', methods: ['GET'])]
    public function data(): JsonResponse
    {
        // 'jsonSuccess' injecté par ApiControllerExtension
        return $this->jsonSuccess([
            'message' => 'ok',
            'flash'   => $this->getFlash('success'),
        ]);
    }
}
```

---

## Structure des fichiers

```
neo/Core/Controller/
├── AbstractController.php              # Classe de base avec délégation dynamique
├── Exception/
│   └── AbstractControllerException.php
├── Interface/
│   └── ControllerExtensionInterface.php  # Contrat pour les extensions
└── Commands/
    └── MakeControllerCommand.php          # make:controller
```
