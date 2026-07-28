# Routing

Le module `Routing` est responsable de la correspondance entre les requêtes HTTP entrantes et les méthodes de contrôleurs PHP. Il s'appuie sur des **attributs PHP 8** pour déclarer les routes directement sur les classes et méthodes, gère un système de cache pour la production, et expose des helpers dans les contrôleurs et les vues Twig.

---

## Table des matières

1. [Vue d'ensemble](#vue-densemble)
2. [RouterModule](#routermodule)
3. [Attributs de route](#attributs-de-route)
   - [MainRoute](#mainroute)
   - [Route](#route)
   - [RateLimit](#ratelimit)
   - [Maintenance](#maintenance)
4. [RouterManager](#routermanager)
5. [RouteCollection](#routecollection)
6. [Extensions](#extensions)
   - [RouterControllerExtension](#routercontrollerextension)
   - [RouterViewExtension](#routerviewextension)
7. [Commande debug:router](#commande-debugrouter)
8. [Gestion des erreurs](#gestion-des-erreurs)

---

## Vue d'ensemble

```
Requête HTTP
     │
     ▼
RouterManager::dispatch()
     ├── Scan des contrôleurs (ou lecture du cache JSON en prod)
     ├── Matching du chemin avec compilePattern()
     ├── MiddlewareManager::run()   ← vérification des middlewares
     └── Résolution des paramètres + invoke du contrôleur
```

---

## RouterModule

Fichier : `RouterModule.php`

### Dépendances

```php
public function dependencies(): array
{
    return [
        ConfigModule::class,
        ViewModule::class,
    ];
}
```

### Enregistrement

Le module enregistre le `RouterManager` dans le conteneur DI :

```php
public function register(Container $container): void
{
    $container->set(RouterManager::class, fn(Container $c) => new RouterManager($c));
}
```

En mode CLI, `init()` retourne le module lui-même (le routeur n'est pas utile en console). En mode HTTP, il retourne l'instance de `RouterManager`, qui scanne les contrôleurs au moment de sa construction.

---

## Attributs de route

### MainRoute

Fichier : `Attribute/MainRoute.php`

`#[MainRoute]` s'applique à une **classe** de contrôleur et définit un préfixe de chemin et de nom pour toutes les routes de la classe.

```php
use Neo\Core\Routing\Attribute\MainRoute;
use Neo\Core\Routing\Attribute\Route;

#[MainRoute(path: '/admin', name: 'admin')]
class AdminController extends AbstractController
{
    // Route finale : GET /admin/dashboard
    // Nom final  : admin.dashboard
    #[Route(path: '/dashboard', name: 'dashboard')]
    public function dashboard(): Response { ... }

    // Route finale : DELETE /admin/users/{id}
    // Nom final  : admin.delete_user
    #[Route(path: '/users/{id}', name: 'delete_user', methods: ['DELETE'])]
    public function deleteUser(int $id): Response { ... }
}
```

| Paramètre | Type | Description |
|---|---|---|
| `path` | `string` | Préfixe de chemin (le `/` final est supprimé automatiquement) |
| `name` | `string` | Préfixe de nom (un `.` est ajouté comme séparateur) |

### Route

Fichier : `Attribute/Route.php`

`#[Route]` s'applique à une **méthode publique** de contrôleur.

```php
// Route simple
#[Route(path: '/articles', name: 'article.list')]
public function list(): Response { ... }

// Route avec méthodes HTTP multiples
#[Route(path: '/articles', name: 'article.create', methods: ['POST'])]
public function create(): Response { ... }

// Route avec paramètre dynamique et contrainte regex
#[Route(
    path: '/articles/{slug}',
    name: 'article.show',
    methods: ['GET'],
    requirements: ['slug' => '[a-z0-9\-]+']
)]
public function show(string $slug): Response { ... }

// Route avec paramètre optionnel
#[Route(path: '/archive/{year?}', name: 'archive')]
public function archive(?int $year = null): Response { ... }
```

| Paramètre | Type | Défaut | Description |
|---|---|---|---|
| `path` | `string` | — | Chemin de la route (segments `{param}` ou `{param?}`) |
| `name` | `string` | `''` | Nom unique de la route |
| `methods` | `array` | `['GET']` | Méthodes HTTP acceptées |
| `requirements` | `array` | `[]` | Contraintes regex par paramètre |

### RateLimit

Fichier : `Attribute/RateLimit.php`

`#[RateLimit]` peut être placé sur une **classe** ou une **méthode**. Il limite le nombre de requêtes par IP sur une fenêtre de temps.

```php
use Neo\Core\Routing\Attribute\RateLimit;

// Limite toute la classe à 30 requêtes par minute
#[RateLimit(maxAttempts: 30, decaySeconds: 60)]
class ApiController extends AbstractController
{
    // Limite spécifique à cette action : 5 tentatives par minute
    #[RateLimit(maxAttempts: 5, decaySeconds: 60, message: 'Trop de tentatives de connexion.')]
    #[Route(path: '/login', name: 'api.login', methods: ['POST'])]
    public function login(): Response { ... }
}
```

| Paramètre | Type | Défaut | Description |
|---|---|---|---|
| `maxAttempts` | `int` | `60` | Nombre maximum de requêtes |
| `decaySeconds` | `int` | `60` | Durée de la fenêtre en secondes |
| `message` | `string` | `'Too many requests...'` | Message d'erreur retourné (429) |

### Maintenance

Fichier : `Attribute/Maintenance.php`

`#[Maintenance]` peut être placé sur une **classe** (tout le contrôleur) ou une **méthode** (route spécifique). Quand la route est touchée, le `MiddlewareManager` retourne une réponse 503.

```php
use Neo\Core\Routing\Attribute\Maintenance;

// Tout le contrôleur en maintenance
#[Maintenance(message: 'Mise à jour en cours, revenez dans quelques minutes.')]
class ShopController extends AbstractController { ... }

// Seulement une action en maintenance
#[Maintenance]
#[Route(path: '/checkout', name: 'shop.checkout', methods: ['POST'])]
public function checkout(): Response { ... }
```

Si le fichier de vue `maintenance.html.twig` existe, il est rendu avec la variable `message`. Sinon, le message texte brut est retourné directement.

---

## RouterManager

Fichier : `RouterManager.php`

### Scan des contrôleurs

Au démarrage, le `RouterManager` parcourt récursivement le répertoire `controllersPath` (enregistré dans le conteneur DI). Il extrait le FQCN de chaque fichier PHP, utilise le `ScannerAttributeManager` pour lire les attributs `#[MainRoute]` (sur la classe) et `#[Route]` (sur les méthodes publiques), puis peuple la `RouteCollection`.

### Cache en production

En mode `prod` (environment != 'dev'), les routes sont mises en cache dans un fichier JSON :

```
storage/var/cache/router/routes.json
```

Au prochain démarrage, ce fichier est lu directement et le scan PHP est évité. Le cache est invalidé manuellement (en supprimant le fichier) ou lors d'un déploiement.

En mode `dev`, le scan est effectué à chaque requête et les conflits de routes déclenchent un `E_USER_WARNING`.

### Dispatch d'une requête

```php
$response = $routerManager->dispatch($request, $response);
```

Algorithme de dispatch :

1. Normalisation de la méthode HTTP et du chemin.
2. Pour chaque route enregistrée : tentative de matching via `compilePattern()`.
3. Si le chemin correspond mais pas la méthode HTTP : exception 405.
4. Si aucune route ne correspond : exception 404.
5. Si une route correspond : exécution des middlewares, puis injection des paramètres dans la méthode du contrôleur.

### Injection des paramètres dans le contrôleur

Le `RouterManager` utilise la réflexion pour injecter les paramètres dans les méthodes de contrôleur :

1. **Paramètre de route** (ex. `$id`) : injecté depuis les captures du pattern.
2. **Type non-primitif** (ex. `Request $request`) : résolu depuis le conteneur DI.
3. **Valeur par défaut** : utilisée si le paramètre a une valeur par défaut définie.

```php
#[Route(path: '/users/{id}', name: 'user.show')]
public function show(int $id, Request $request): Response
{
    // $id est injecté depuis l'URL
    // $request est résolu depuis le conteneur DI
}
```

### Compilation de patterns

Les segments dynamiques sont compilés en expressions régulières avec capture nommée :

| Segment | Regex générée | Optionnel |
|---|---|---|
| `{id}` | `/(?P<id>[^/]+)` | Non |
| `{slug}` avec `requirements: ['slug' => '[a-z0-9\-]+']` | `/(?P<slug>[a-z0-9\-]+)` | Non |
| `{year?}` | `(?:/(?P<year>[^/]+))?` | Oui |

### Génération d'URL

```php
// Depuis n'importe où avec accès au RouterManager
$url = $routerManager->generateUrl('article.show', ['slug' => 'mon-article']);
// Résultat : '/articles/mon-article'

// Paramètres optionnels non fournis → segment supprimé
$url = $routerManager->generateUrl('archive'); // '/archive'
```

---

## RouteCollection

Fichier : `Collection/RouteCollection.php`

La `RouteCollection` est la structure de données interne du routeur. Elle organise les routes par méthode HTTP puis par chemin.

```php
// Structure interne
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

### Sérialisation (cache)

```php
// Sérialisation vers JSON (prod)
$json = json_encode($collection->toArray());

// Désérialisation depuis JSON
$collection = RouteCollection::fromArray(json_decode($json, true));
```

---

## Extensions

### RouterControllerExtension

Fichier : `Extension/RouterControllerExtension.php`

Cette extension est automatiquement appliquée à tous les contrôleurs qui étendent `AbstractController`. Elle ajoute les méthodes suivantes :

```php
// Obtenir le chemin d'une route nommée
$path = $this->getRoutePath('article.show', ['slug' => 'mon-article']);
// Résultat : '/articles/mon-article'

// Obtenir l'URL de retour (referrer ou fallback)
$back = $this->getRedirectBack('home');
$back = $this->getRedirectBack(null); // fallback sur '/'

// Redirections
return $this->redirectToRoute('dashboard');
return $this->redirectToRoute('article.show', ['slug' => 'test']);
return $this->redirectToPath('/chemin/absolu', 301);
return $this->redirectBack('home', [], 302);
```

### RouterViewExtension

Fichier : `Extension/RouterViewExtension.php`

Cette extension ajoute deux fonctions globales dans les templates Twig :

```twig
{# Générer un lien depuis le nom d'une route #}
<a href="{{ path('article.show', {slug: 'mon-article'}) }}">Lire l'article</a>
<a href="{{ path('home') }}">Accueil</a>

{# Obtenir le nom de la route courante (utile pour les menus actifs) #}
{% if currentRoute() == 'admin.dashboard' %}
    <li class="active">Dashboard</li>
{% endif %}
```

---

## Commande debug:router

Fichier : `Commands/DebugRouterCommand.php`

La commande `debug:router` affiche toutes les routes enregistrées pour un projet, avec colorisation par méthode HTTP.

```bash
# Afficher toutes les routes du projet "app"
php neo debug:router --project=app

# Filtrer par méthode HTTP
php neo debug:router --project=app --method=POST

# Filtrer par nom de route
php neo debug:router --project=app --name=admin

# Filtrer par chemin
php neo debug:router --project=app --path=/api
```

Exemple de sortie :

```
Routes for app (12)

  GET     /                                  home                    App\Controller\HomeController::index
  GET     /admin/dashboard                   admin.dashboard         App\Controller\AdminController::dashboard
  GET     /articles                          article.list            App\Controller\ArticleController::list
  POST    /articles                          article.create          App\Controller\ArticleController::create
  GET     /articles/{slug}                   article.show            App\Controller\ArticleController::show
  DELETE  /admin/users/{id}                  admin.delete_user       App\Controller\AdminController::deleteUser
```

Couleurs : `GET` vert, `POST` jaune, `PUT`/`PATCH` cyan, `DELETE` rouge.

---

## Gestion des erreurs

| Situation | Exception | Code HTTP |
|---|---|---|
| Aucune route ne correspond | `RouteNotFoundException` | 404 |
| Chemin connu, mauvaise méthode HTTP | `RouterException` | 405 |
| Paramètre de contrôleur non injectable | `RouterException` | 500 |
| Erreur dans le contrôleur | `RouterException` (wrapping) | 500 |

Les exceptions `RouteNotFoundException` et `RouterException` étendent `FrameworkException` et sont gérées par le gestionnaire d'erreurs global du framework.
