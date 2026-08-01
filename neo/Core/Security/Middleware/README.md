# Middleware

Le sous-module `Middleware` fournit un pipeline d'autorisation déclaratif par attributs PHP 8, avec des middlewares intégrés et le support de middlewares personnalisés.

---

## Sommaire

1. [Structure](#structure)
2. [MiddlewareInterface](#middlewareinterface)
3. [MiddlewareManager](#middlewaremanager)
4. [Attribut `#[Middleware]`](#attribut-middleware)
5. [Attribut `#[IsGranted]`](#attribut-isgranted)
6. [Middlewares intégrés](#middlewares-intégrés)
7. [Créer un middleware personnalisé](#créer-un-middleware-personnalisé)
8. [Commande CLI](#commande-cli)

---

## Structure

```
Middleware/
├── MiddlewareManager.php               # Orchestrateur du pipeline
├── MiddlewareModule.php                # Enregistrement DI
├── Interface/
│   └── MiddlewareInterface.php         # Contrat : handle(): bool
├── Attribute/
│   ├── Middleware.php                  # Attribut déclaratif (répétable)
│   └── IsGranted.php                   # Raccourci pour les rôles
├── Default/
│   ├── AuthMiddleware.php              # Vérification de connexion
│   ├── GuestMiddleware.php             # Inverse de Auth
│   ├── IsGrantedMiddleware.php         # Vérification de rôle (logique OU)
│   ├── RoleMiddleware.php              # Rôle unique
│   ├── CsrfMiddleware.php              # Validation du token CSRF
│   ├── RateLimitMiddleware.php         # Limite par IP + chemin
│   ├── AuthRateLimitMiddleware.php     # Limite pour les formulaires de login
│   └── ExampleMiddleware.php           # Template de départ
├── Exception/
│   └── MiddlewareException.php         # 403 Forbidden
├── Extension/
│   └── MiddlewareControllerExtension.php # Injecte getMiddleware()
└── Commands/
    └── MakeMiddlewareCommand.php       # CLI : make:middleware
```

---

## MiddlewareInterface

**Fichier :** `Interface/MiddlewareInterface.php`

Tout middleware doit implémenter cette interface :

```php
interface MiddlewareInterface
{
    /**
     * Retourne true si la requête peut continuer, false sinon.
     */
    public function handle(): bool;
}
```

---

## MiddlewareManager

**Fichier :** `MiddlewareManager.php`

Chef d'orchestre du pipeline. Appelé automatiquement par le `RouterManager` avant chaque invocation de contrôleur.

### Découverte des middlewares

Le manager lit les attributs `#[Middleware]`, `#[IsGranted]` et `#[RateLimit]` sur la **classe** du contrôleur ET sur la **méthode** ciblée. Les middlewares de classe sont appliqués en premier, puis ceux de la méthode. L'ordre final est déterminé par le champ `priority` (valeur plus haute = exécutée en premier).

### Exécution

```php
$response = $middlewareManager->run($controllerClass, $methodName);

if ($response !== null) {
    // Un middleware a bloqué la requête
    $response->send();
    return;
}
// La requête peut continuer normalement
```

### Comportements en cas d'échec

| `onError` | Comportement |
|-----------|--------------|
| `'block'` (défaut) | Lève une `MiddlewareException` (403) |
| `'soft'` | Ajoute un message flash de warning, laisse passer |
| Avec `redirect` défini | Redirige vers la route nommée (302) avec message flash optionnel |

### Vérification sans exécution

```php
// Vérifie si une route est accessible sans déclencher les effets de bord
$canAccess = $middlewareManager->isAccessible(MonController::class, 'edit');
```

### Inspection des erreurs

```php
// Récupérer tous les messages d'erreur après un run()
$errors = $middlewareManager->getErrors();

// Par middleware spécifique
$errors = $middlewareManager->getErrors(AuthMiddleware::class);

// Vérifier s'il y a eu des échecs
if ($middlewareManager->hasError()) { /* ... */ }

// Résultats d'exécution d'un middleware (tableau de bool)
$results = $middlewareManager->getMiddleware(AuthMiddleware::class); // [true, false, ...]
```

### Mode maintenance

Avant d'exécuter les middlewares, le `MiddlewareManager` vérifie l'attribut `#[Maintenance]`. Si présent (sur la méthode ou la classe), il retourne immédiatement une réponse **503** avec la vue `maintenance.html.twig` (si elle existe).

---

## Attribut `#[Middleware]`

**Fichier :** `Attribute/Middleware.php`

Répétable (`IS_REPEATABLE`), applicable sur une classe ou une méthode.

```php
use Neo\Core\Security\Middleware\Attribute\Middleware;

// Sur une classe : appliqué à toutes les routes du contrôleur
#[Middleware(
    use: AuthMiddleware::class,
    message: 'Vous devez être connecté.',
    onError: 'block',
    redirect: 'login',   // Nom de route pour la redirection
    params: [],
    priority: 10         // Plus haute priorité = exécuté en premier
)]
class DashboardController extends AbstractController { ... }

// Sur une méthode uniquement
#[Middleware(use: CsrfMiddleware::class)]
#[Route('/settings', 'POST')]
public function update(): Response { ... }

// Plusieurs middlewares empilés
#[Middleware(use: AuthMiddleware::class, priority: 10)]
#[Middleware(use: CsrfMiddleware::class, priority: 5)]
class SecureController extends AbstractController { ... }
```

| Paramètre | Type | Défaut | Description |
|-----------|------|--------|-------------|
| `use` | `class-string` | — | Classe du middleware à exécuter |
| `message` | `string` | `''` | Message en cas d'échec |
| `onError` | `string` | `'block'` | `'block'` ou `'soft'` |
| `redirect` | `string\|null` | `null` | Nom de route pour la redirection |
| `params` | `array` | `[]` | Paramètres passés au constructeur du middleware |
| `priority` | `int` | `0` | Ordre d'exécution (décroissant) |

---

## Attribut `#[IsGranted]`

**Fichier :** `Attribute/IsGranted.php`

Raccourci déclaratif pour restreindre l'accès à certains rôles. Instancie automatiquement un `IsGrantedMiddleware`.

```php
use Neo\Core\Security\Middleware\Attribute\IsGranted;

// Accès réservé aux administrateurs
#[IsGranted(roles: ['admin'])]
class AdminController extends AbstractController { ... }

// Plusieurs rôles autorisés (logique OU)
#[IsGranted(
    roles: ['admin', 'moderator'],
    message: 'Accès réservé aux modérateurs.',
    onError: 'block',
    redirect: 'home'
)]
#[Route('/moderation', 'GET')]
public function moderation(): Response { ... }
```

| Paramètre | Type | Défaut | Description |
|-----------|------|--------|-------------|
| `roles` | `array` | — | Liste des rôles autorisés (logique OU) |
| `message` | `string` | `''` | Message en cas d'échec |
| `onError` | `string` | `'block'` | Comportement en cas d'échec |
| `redirect` | `string\|null` | `null` | Route de redirection |

---

## Middlewares intégrés

### AuthMiddleware

**Fichier :** `Default/AuthMiddleware.php`

Vérifie que l'utilisateur est connecté via l'`AuthManager`.

```php
#[Middleware(use: AuthMiddleware::class, redirect: 'login')]
class DashboardController extends AbstractController { ... }
```

### GuestMiddleware

**Fichier :** `Default/GuestMiddleware.php`

Inverse de `AuthMiddleware` — n'autorise que les utilisateurs **non** connectés.

```php
#[Middleware(use: GuestMiddleware::class, redirect: 'dashboard')]
class LoginController extends AbstractController { ... }
```

### IsGrantedMiddleware

**Fichier :** `Default/IsGrantedMiddleware.php`

Vérifie que l'utilisateur possède **au moins un** des rôles requis (logique OU). Si aucun rôle n'est requis, l'accès est accordé.

Préférer l'attribut `#[IsGranted]` pour une utilisation déclarative.

### RoleMiddleware

**Fichier :** `Default/RoleMiddleware.php`

Vérifie un **rôle unique**. Utilisé avec `params` dans l'attribut `#[Middleware]`.

```php
#[Middleware(
    use: RoleMiddleware::class,
    params: ['role' => 'editor'],
    message: 'Accès réservé aux éditeurs.'
)]
public function edit(): Response { ... }
```

### CsrfMiddleware

**Fichier :** `Default/CsrfMiddleware.php`

Valide le token CSRF pour toutes les requêtes non-sûres (`POST`, `PUT`, `PATCH`, `DELETE`). Les méthodes `GET`, `HEAD`, `OPTIONS` sont ignorées.

```php
#[Middleware(use: CsrfMiddleware::class)]
#[Route('/settings', 'POST')]
public function update(): Response { ... }
```

### RateLimitMiddleware

**Fichier :** `Default/RateLimitMiddleware.php`

Limite le nombre de requêtes par IP et par chemin. Utilise le `CacheManager`. Lève une `FrameworkException` (429) quand la limite est atteinte.

**Clé de cache :** `rate_limit:<md5(ip:path)>`, TTL égal à `decaySeconds`.

```php
// Via l'attribut RateLimit (raccourci)
#[RateLimit(maxAttempts: 5, decaySeconds: 60)]
#[Route('/login', 'POST')]
public function login(): Response { ... }

// Via l'attribut Middleware
#[Middleware(
    use: RateLimitMiddleware::class,
    params: ['maxAttempts' => 100, 'decaySeconds' => 3600],
    message: 'Quota API dépassé.'
)]
class ApiController extends AbstractController { ... }
```

### AuthRateLimitMiddleware

**Fichier :** `Default/AuthRateLimitMiddleware.php`

Variante spécialisée pour les formulaires de login. Limite par **IP + valeur du champ identifiant** (ex. email) plutôt que par chemin.

```php
#[Middleware(
    use: AuthRateLimitMiddleware::class,
    params: ['maxAttempts' => 5, 'decaySeconds' => 300],
    message: 'Trop de tentatives. Réessayez dans 5 minutes.'
)]
#[Route('/login', 'POST')]
public function login(): Response { ... }
```

---

## Créer un middleware personnalisé

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

**Utilisation dans un contrôleur :**

```php
use Neo\Core\Security\Middleware\Attribute\Middleware;
use App\Middlewares\BusinessHoursMiddleware;

#[Middleware(
    use: BusinessHoursMiddleware::class,
    message: 'Ce service n\'est disponible qu\'entre 8h et 18h.',
    onError: 'block'
)]
#[Route('/support', 'GET')]
public function support(): Response { ... }
```

Le `MiddlewareManager` instancie le middleware via le conteneur DI — toutes les dépendances déclarées dans le constructeur sont injectées automatiquement.

---

## Commande CLI

| Commande | Description |
|----------|-------------|
| `make:middleware` | Génère un squelette de middleware dans le projet |

```bash
php bin/neo make:middleware MonMiddleware --project=Blog
# Génère : src/Blog/App/Middlewares/MonMiddleware.php

php bin/neo make:middleware AdminOnly --project=Blog --dir=Admin
# Génère : src/Blog/App/Middlewares/Admin/AdminOnlyMiddleware.php
```

Le suffixe `Middleware` est ajouté automatiquement si absent. L'option `--force` écrase un fichier existant.
