# Security

Le module `Security` regroupe trois sous-systèmes complémentaires qui sécurisent les applications NeoPHP :

- **Auth** : authentification par session ou JWT, gestion des rôles, hachage de mots de passe.
- **CSRF** : protection contre les attaques Cross-Site Request Forgery via tokens de session.
- **Middleware** : pipeline d'autorisation déclaratif par attributs PHP 8, avec middlewares intégrés et support de middlewares personnalisés.

---

## Table des matières

1. [Auth](#auth)
   - [AuthManager](#authmanager)
   - [SessionGuard](#sessionguard)
   - [TokenGuard](#tokenguard)
   - [JwtManager](#jwtmanager)
   - [PasswordManager](#passwordmanager)
   - [AuthControllerExtension](#authcontrollerextension)
   - [Configuration](#configuration-auth)
2. [CSRF](#csrf)
   - [CsrfManager](#csrfmanager)
   - [CsrfTokenManager](#csrftokenmanager)
   - [CsrfViewExtension](#csrfviewextension)
   - [CsrfMiddleware](#csrfmiddleware)
3. [Middleware](#middleware)
   - [MiddlewareInterface](#middlewareinterface)
   - [MiddlewareManager](#middlewaremanager)
   - [Attribut Middleware](#attribut-middleware)
   - [Attribut IsGranted](#attribut-isgranted)
   - [Middlewares intégrés](#middlewares-intégrés)
   - [Créer un middleware personnalisé](#créer-un-middleware-personnalisé)

---

## Auth

### AuthManager

Fichier : `Auth/AuthManager.php`

L'`AuthManager` est le point d'entrée unique pour l'authentification. Il lit la configuration depuis `config/auth.php` et instancie automatiquement le guard approprié (`session` ou `token`).

#### API publique

```php
// Tentative de connexion avec des identifiants
$success = $auth->attempt(['email' => 'user@example.com', 'password' => 'secret']);

// Connexion directe d'un objet utilisateur
$auth->login($userObject);

// Déconnexion
$auth->logout();

// Vérifier si l'utilisateur est connecté
if ($auth->check()) { ... }

// Obtenir l'utilisateur courant (null si non connecté)
$user = $auth->user();

// Vérifier un rôle
if ($auth->hasRole('admin')) { ... }

// Générer un token JWT (guard 'token' uniquement)
$token = $auth->generateToken($userObject);
```

#### Résolution du guard

```php
// Dans AuthManager::resolveGuard()
return match($guardType) {
    'token'   => new TokenGuard(...),
    default   => new SessionGuard(...),
};
```

Si la clé `enabled` vaut `false` dans la configuration, l'`AuthManager` est instancié sans guard. Toute méthode nécessitant un guard (`attempt`, `login`, `logout`, `generateToken`) lèvera une `AuthException`.

---

### SessionGuard

Fichier : `Auth/Guard/SessionGuard.php`

Le `SessionGuard` persiste l'authentification en session PHP. Il stocke l'identifiant de l'utilisateur et la date de dernière activité.

**Clés de session utilisées :**

| Clé | Contenu |
|---|---|
| `_auth_user_id` | Identifiant primaire de l'utilisateur |
| `_auth_last_activity` | Timestamp Unix de la dernière activité |

**Fonctionnement de `attempt()` :**

1. Vérifie que les identifiants contiennent les champs `identifier` et `password`.
2. Récupère l'utilisateur par son identifiant via le repository ORM.
3. Vérifie le mot de passe avec `PasswordManager::verify()`.
4. En cas de succès : régénère la session, stocke l'ID et le timestamp.

**Expiration de session :**

```php
// Dans check()
if ((time() - $lastActivity) > $this->timeout) {
    $this->logout(); // supprime les clés de session
    return false;
}
// Renouvellement automatique de la dernière activité
$this->session->set(self::SESSION_LAST_ACTIVITY_KEY, time());
```

Le timeout par défaut est de 1800 secondes (30 minutes), configurable via `options.timeout` dans `auth.config.php`.

---

### TokenGuard

Fichier : `Auth/Guard/TokenGuard.php`

Le `TokenGuard` authentifie les requêtes via un **token JWT** transmis dans le header `Authorization: Bearer <token>`.

**Fonctionnement :**

```php
// Extraction du token depuis le header HTTP
private function extractToken(): ?string
{
    $header = $this->request->header('Authorization');
    if (!$header || !str_starts_with($header, 'Bearer ')) {
        return null;
    }
    return substr($header, 7);
}
```

**Génération d'un token :**

```php
// Le payload contiendra uniquement 'sub' => id de l'utilisateur
public function generateToken(object $user): string
{
    $id = $this->em->getClassMetadata($user::class)->getIdentifierValue($user);
    return $this->jwtManager->generate(['sub' => $id]);
}
```

**Récupération de l'utilisateur :**

Le `user()` décode le token, extrait le claim `sub` et charge l'entité depuis la base de données via l'`EntityManager`.

**Différence avec `SessionGuard` :**

- `login()` est une méthode vide (stateless).
- `logout()` efface uniquement le payload mis en cache en mémoire.
- Pas de stockage côté serveur.

---

### JwtManager

Fichier : `Auth/JwtManager.php`

Le `JwtManager` gère la génération, le décodage et la validation des tokens JWT **sans dépendance externe** (implémentation maison avec HMAC-SHA256).

#### Génération

```php
$jwt = new JwtManager(
    secret: 'ma-cle-secrete-tres-longue',
    expiration: 3600,  // 1 heure
    algorithm: 'HS256'
);

$token = $jwt->generate(['sub' => 42, 'role' => 'admin']);
// → header.payload.signature (base64url)
```

Le payload généré contient automatiquement les claims `iat` (issued at) et `exp` (expiration).

#### Décodage et validation

```php
// Décode et vérifie la signature + l'expiration
$payload = $jwt->decode($token);
// Retourne : ['sub' => 42, 'role' => 'admin', 'iat' => ..., 'exp' => ...]

// Vérification silencieuse (pas d'exception)
$isValid = $jwt->isValid($token); // true | false
```

**Exceptions levées par `decode()` :**

| Situation | Message |
|---|---|
| Format invalide (pas 3 parties) | `Invalid token format.` |
| Signature incorrecte | `Invalid token signature.` |
| Payload non décodable | `Invalid token payload.` |
| Token expiré | `The token has expired.` |

**Sécurité :** La comparaison de signature utilise `hash_equals()` pour prévenir les attaques temporelles.

---

### PasswordManager

Fichier : `Auth/PasswordManager.php`

Le `PasswordManager` encapsule les fonctions natives PHP de gestion des mots de passe.

```php
$pm = new PasswordManager();

// Hachage (bcrypt, cost 12)
$hash = $pm->hash('mon-mot-de-passe');

// Vérification
$isValid = $pm->verify('mon-mot-de-passe', $hash); // true

// Vérifier si le hash doit être recalculé (après changement de paramètres)
if ($pm->needsRehash($hash)) {
    $user->setPassword($pm->hash($plainPassword));
}

// Générer un mot de passe aléatoire (hex, 12 octets = 24 caractères)
$generated = $pm->generate(12);

// Infos sur l'algorithme utilisé
$info = $pm->getInfo($hash);
// ['algo' => PASSWORD_BCRYPT, 'algoName' => 'bcrypt', 'options' => ['cost' => 12]]
```

**Paramètres :** algorithme `PASSWORD_DEFAULT` (bcrypt), cost 12.

---

### AuthControllerExtension

Fichier : `Auth/Extension/AuthControllerExtension.php`

Cette extension injecte deux méthodes dans tous les contrôleurs :

```php
// Accéder à l'AuthManager
$this->auth()->check();
$this->auth()->user();
$this->auth()->attempt(['email' => $email, 'password' => $password]);
$this->auth()->logout();

// Accéder au PasswordManager
$hash = $this->getPasswordManager()->hash($plainPassword);
```

---

### Configuration Auth

Fichier de configuration : `config/auth.php` (ou équivalent selon le projet)

```php
return [
    'enabled'    => true,

    // Garde utilisé : 'session' ou 'token'
    'guard'      => 'session',

    // Classe du modèle utilisateur (FQCN)
    'model'      => App\Entity\User::class,

    // Champ utilisé comme identifiant de connexion
    'identifier' => 'email',

    // Champ du mot de passe
    'password'   => 'password',

    // Configuration des rôles (optionnel)
    'role' => [
        'relation' => 'role',       // propriété de l'entité User
        'model'    => App\Entity\Role::class,
        'field'    => 'name',       // champ du rôle à comparer
    ],

    // Options spécifiques au guard
    'options' => [
        // Pour le guard 'session'
        'timeout' => 1800,

        // Pour le guard 'token'
        'secret'     => 'votre-cle-jwt-secrete',
        'expiration' => 3600,
        'algorithm'  => 'HS256',
    ],
];
```

---

## CSRF

### CsrfManager

Fichier : `Csrf/CsrfManager.php`

Le `CsrfManager` gère un **token de session unique** par session utilisateur. Il est utilisé par le `CsrfMiddleware` pour protéger les formulaires.

```php
// Générer ou récupérer le token de la session courante
$token = $csrf->generate();

// Lire le token sans le créer
$token = $csrf->token();

// Valider le token envoyé dans la requête
$isValid = $csrf->validate();

// Forcer la régénération du token
$csrf->refresh();
```

**Sources du token dans la requête :**

La validation accepte le token depuis deux sources (dans l'ordre) :

1. `body('_csrf_token')` : champ caché dans un formulaire HTML.
2. `header('X-CSRF-Token')` : header HTTP (pour les requêtes AJAX).

**Comparaison sécurisée :** `hash_equals()` est utilisé pour éviter les attaques temporelles.

**Exemple d'utilisation dans un contrôleur :**

```php
#[Route(path: '/profile/edit', name: 'profile.edit', methods: ['POST'])]
public function edit(): Response
{
    // Le CsrfMiddleware valide automatiquement le token si configuré
    // Sinon, validation manuelle :
    if (!$this->csrfManager->validate()) {
        throw new \RuntimeException('Token CSRF invalide.');
    }
    // ...
}
```

---

### CsrfTokenManager

Fichier : `Csrf/CsrfTokenManager.php`

Le `CsrfTokenManager` est une alternative plus avancée qui gère des **tokens nommés avec expiration**. Contrairement au `CsrfManager`, il peut gérer plusieurs tokens simultanés (un par formulaire).

```php
// Générer un token pour un formulaire spécifique (expire dans 3600s)
$token = $csrfTokenManager->generateToken('contact_form', expiry: 3600);
$tokenValue = $token->getValue(); // chaîne hex de 64 caractères

// Récupérer un token existant
$token = $csrfTokenManager->getToken('contact_form'); // CsrfToken|null

// Valider et consommer le token (invalidate: true = suppression après validation)
$isValid = $csrfTokenManager->validateToken('contact_form', $submittedValue, invalidate: true);
```

**Stockage :** `$_SESSION['_csrf_tokens']['<id>']` contient un objet `CsrfToken`.

**Token expiré :** si le token est expiré lors de la validation, il est supprimé de la session et la méthode retourne `false`.

---

### CsrfViewExtension

Fichier : `Csrf/Extension/CsrfViewExtension.php`

Cette extension Twig expose la fonction `csrf_token()` dans les templates :

```twig
{# Dans un formulaire HTML #}
<form method="POST" action="{{ path('profile.edit') }}">
    <input type="hidden" name="_csrf_token" value="{{ csrf_token() }}">
    {# ... champs du formulaire ... #}
    <button type="submit">Enregistrer</button>
</form>

{# Avec un identifiant personnalisé #}
<input type="hidden" name="_csrf_token" value="{{ csrf_token('contact_form') }}">
```

Si le token n'existe pas encore en session, il est créé automatiquement.

---

### CsrfMiddleware

Fichier : `Middleware/Default/CsrfMiddleware.php`

Le `CsrfMiddleware` protège automatiquement toutes les routes auxquelles il est appliqué. Les méthodes "sûres" (`GET`, `HEAD`, `OPTIONS`) sont ignorées.

```php
// Application via l'attribut Middleware
use Neo\Core\Security\Middleware\Attribute\Middleware;
use Neo\Core\Security\Middleware\Default\CsrfMiddleware;

#[Middleware(use: CsrfMiddleware::class, message: 'Token CSRF manquant ou invalide.')]
class MonController extends AbstractController { ... }
```

---

## Middleware

### MiddlewareInterface

Fichier : `Middleware/Interface/MiddlewareInterface.php`

Tout middleware doit implémenter cette interface minimaliste :

```php
namespace Neo\Core\Security\Middleware\Interface;

interface MiddlewareInterface
{
    /**
     * Exécute la logique du middleware.
     * Retourne true si la requête peut continuer, false sinon.
     */
    public function handle(): bool;
}
```

---

### MiddlewareManager

Fichier : `Middleware/MiddlewareManager.php`

Le `MiddlewareManager` est le chef d'orchestre du pipeline d'autorisation. Il est appelé automatiquement par le `RouterManager` avant d'invoquer chaque contrôleur.

#### Découverte des middlewares

Le manager lit les attributs `#[Middleware]`, `#[IsGranted]` et `#[RateLimit]` sur la classe du contrôleur ET sur la méthode ciblée. Les middlewares de classe sont appliqués en premier, puis ceux de la méthode. L'ordre final est déterminé par le champ `priority` (valeur plus haute = exécutée en premier).

#### Exécution

```php
$response = $middlewareManager->run($controllerClass, $methodName);

if ($response !== null) {
    // Un middleware a bloqué la requête et retourné une réponse
    $response->send();
    return;
}
// La requête peut continuer normalement
```

#### Comportements en cas d'échec

Le comportement lors d'un échec est contrôlé par le paramètre `onError` de l'attribut :

| `onError` | Comportement |
|---|---|
| `'block'` (défaut) | Lève une `MiddlewareException` (403) |
| `'soft'` | Ajoute un message flash de warning, laisse passer |
| Toujours si `redirect` défini | Redirige vers la route nommée (302) avec message flash |

#### Vérification sans exécution

```php
// Vérifier si une route est accessible sans déclencher les effets de bord
$canAccess = $middlewareManager->isAccessible(MonController::class, 'edit');
```

#### Inspection des erreurs

```php
// Après un run(), récupérer les messages d'erreur
$errors = $middlewareManager->getErrors();
$errors = $middlewareManager->getErrors(AuthMiddleware::class); // par middleware

if ($middlewareManager->hasError()) {
    // Au moins un middleware a échoué
}

// Récupérer les résultats d'exécution d'un middleware
$results = $middlewareManager->getMiddleware(AuthMiddleware::class); // [true, false, ...]
```

#### Mode maintenance

Avant d'exécuter les middlewares, le `MiddlewareManager` vérifie l'attribut `#[Maintenance]`. Si présent (sur la méthode ou la classe), il retourne immédiatement une réponse 503 avec la vue `maintenance.html.twig` (si elle existe).

---

### Attribut Middleware

Fichier : `Middleware/Attribute/Middleware.php`

L'attribut `#[Middleware]` est répétable (`IS_REPEATABLE`) et peut être placé sur une classe ou une méthode.

```php
use Neo\Core\Security\Middleware\Attribute\Middleware;

// Sur une classe : appliqué à toutes les routes du contrôleur
#[Middleware(
    use: AuthMiddleware::class,
    message: 'Vous devez être connecté.',
    onError: 'block',
    redirect: 'login',      // nom de route pour la redirection
    params: [],
    priority: 10            // plus haute priorité = exécuté en premier
)]
class DashboardController extends AbstractController { ... }

// Sur une méthode : appliqué uniquement à cette route
#[Middleware(use: CsrfMiddleware::class)]
#[Route(path: '/settings', name: 'settings.update', methods: ['POST'])]
public function update(): Response { ... }

// Middlewares répétables : plusieurs peuvent être empilés
#[Middleware(use: AuthMiddleware::class, priority: 10)]
#[Middleware(use: CsrfMiddleware::class, priority: 5)]
class SecureController extends AbstractController { ... }
```

| Paramètre | Type | Défaut | Description |
|---|---|---|---|
| `use` | `class-string` | — | Classe du middleware à exécuter |
| `message` | `string` | `''` | Message en cas d'échec |
| `onError` | `string` | `'block'` | `'block'` ou `'soft'` |
| `redirect` | `string\|null` | `null` | Nom de route pour la redirection |
| `params` | `array` | `[]` | Paramètres passés au constructeur du middleware |
| `priority` | `int` | `0` | Ordre d'exécution (décroissant) |

---

### Attribut IsGranted

Fichier : `Middleware/Attribute/IsGranted.php`

`#[IsGranted]` est un raccourci déclaratif pour restreindre l'accès à certains rôles. Il instancie automatiquement un `IsGrantedMiddleware`.

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
#[Route(path: '/moderation', name: 'moderation')]
public function moderation(): Response { ... }
```

| Paramètre | Type | Défaut | Description |
|---|---|---|---|
| `roles` | `array` | — | Liste des rôles autorisés (logique OU) |
| `message` | `string` | `''` | Message en cas d'échec (défaut: `'Access denied.'`) |
| `onError` | `string` | `'block'` | Comportement en cas d'échec |
| `redirect` | `string\|null` | `null` | Route de redirection |

---

### Middlewares intégrés

#### AuthMiddleware

Fichier : `Middleware/Default/AuthMiddleware.php`

Vérifie que l'utilisateur est connecté via l'`AuthManager`.

```php
public function handle(): bool
{
    return $this->auth->check();
}
```

#### IsGrantedMiddleware

Fichier : `Middleware/Default/IsGrantedMiddleware.php`

Vérifie que l'utilisateur possède au moins l'un des rôles requis (logique OU). Si aucun rôle n'est requis, l'accès est accordé.

```php
public function handle(): bool
{
    foreach ($this->roles as $role) {
        if ($this->auth->hasRole($role)) {
            return true;
        }
    }
    return empty($this->roles); // true si aucun rôle requis
}
```

#### RoleMiddleware

Fichier : `Middleware/Default/RoleMiddleware.php`

Vérifie un rôle unique. Plus simple que `IsGrantedMiddleware`, utile avec l'attribut `#[Middleware]` et `params`.

```php
#[Middleware(
    use: RoleMiddleware::class,
    params: ['role' => 'editor'],
    message: 'Accès réservé aux éditeurs.'
)]
public function edit(): Response { ... }
```

#### CsrfMiddleware

Fichier : `Middleware/Default/CsrfMiddleware.php`

Valide le token CSRF pour toutes les requêtes non-sûres (POST, PUT, PATCH, DELETE).

```php
private const array SAFE_METHODS = ['GET', 'HEAD', 'OPTIONS'];

public function handle(): bool
{
    if (in_array($this->request->getMethod(), self::SAFE_METHODS, true)) {
        return true; // méthode sûre, pas de vérification
    }
    return $this->csrfManager->validate();
}
```

#### RateLimitMiddleware

Fichier : `Middleware/Default/RateLimitMiddleware.php`

Limite le nombre de requêtes par IP et par chemin. Utilise le `CacheManager` pour stocker les compteurs. Lève une `FrameworkException` (429) quand la limite est atteinte.

```php
// Via l'attribut RateLimit (raccourci)
#[RateLimit(maxAttempts: 5, decaySeconds: 60)]
#[Route(path: '/login', name: 'login', methods: ['POST'])]
public function login(): Response { ... }

// Via l'attribut Middleware (plus de contrôle)
#[Middleware(
    use: RateLimitMiddleware::class,
    params: ['maxAttempts' => 100, 'decaySeconds' => 3600],
    message: 'Quota API dépassé.'
)]
class ApiController extends AbstractController { ... }
```

**Clé de cache :** `rate_limit:<md5(ip:path)>`, avec TTL égal à `decaySeconds`.

---

### Créer un middleware personnalisé

```php
<?php
declare(strict_types=1);

namespace App\Middleware;

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
        // N'autoriser les accès qu'entre 8h et 18h
        return $hour >= 8 && $hour < 18;
    }
}
```

**Utilisation dans un contrôleur :**

```php
use Neo\Core\Security\Middleware\Attribute\Middleware;
use App\Middleware\BusinessHoursMiddleware;

#[Middleware(
    use: BusinessHoursMiddleware::class,
    message: 'Ce service n\'est disponible qu\'entre 8h et 18h.',
    onError: 'block'
)]
#[Route(path: '/support', name: 'support')]
public function support(): Response { ... }
```

**Avec paramètres personnalisés :**

```php
class TimeWindowMiddleware implements MiddlewareInterface
{
    public function __construct(
        private readonly int $startHour = 8,
        private readonly int $endHour = 18
    ) {}

    public function handle(): bool
    {
        $hour = (int) date('H');
        return $hour >= $this->startHour && $hour < $this->endHour;
    }
}

// Utilisation avec params
#[Middleware(
    use: TimeWindowMiddleware::class,
    params: ['startHour' => 9, 'endHour' => 17]
)]
public function restrictedAction(): Response { ... }
```

Les paramètres définis dans `params` sont passés au constructeur via `$container->make($middlewareClass, $params)`.
