# Auth

Le sous-module `Auth` gère l'authentification des utilisateurs par session PHP ou token JWT, la vérification des rôles et le hachage des mots de passe.

---

## Sommaire

1. [Structure](#structure)
2. [Configuration](#configuration)
3. [AuthManager](#authmanager)
4. [RoleConfig](#roleconfig)
5. [SessionGuard](#sessionguard)
6. [TokenGuard](#tokenguard)
7. [JwtManager](#jwtmanager)
8. [PasswordManager](#passwordmanager)
9. [Extension contrôleur](#extension-contrôleur)
10. [Extension Twig](#extension-twig)

---

## Structure

```
Auth/
├── AuthManager.php                     # Point d'entrée de l'authentification
├── AuthModule.php                      # Enregistrement DI
├── JwtManager.php                      # Génération/validation JWT (HMAC-SHA256)
├── PasswordManager.php                 # Hachage bcrypt
├── RoleConfig.php                      # DTO de configuration des rôles, partagé entre les guards
├── Collector/
│   └── AuthCollector.php              # Collecteur Profiler
├── Exception/
│   ├── AuthException.php              # Erreur d'authentification
│   └── JwtException.php               # Erreur de validation JWT
├── Extension/
│   ├── AuthControllerExtension.php    # Injecte auth() et getPasswordManager()
│   └── AuthViewExtension.php          # Fonctions Twig auth_*
└── Guard/
    ├── Interface/
    │   └── GuardInterface.php
    ├── SessionGuard.php               # Authentification par session
    └── TokenGuard.php                 # Authentification par JWT
```

---

## Configuration

**Fichier :** `src/<Projet>/Config/auth.config.php`

```php
return [
    'enabled'    => true,

    // Guard utilisé : 'session' ou 'token'
    'guard'      => 'session',

    // Classe du modèle utilisateur (FQCN)
    'model'      => App\Entity\User::class,

    // Champ utilisé comme identifiant de connexion
    'identifier' => 'email',

    // Champ du mot de passe
    'password'   => 'password',

    // Configuration des rôles (optionnel)
    'role' => [
        'relation' => 'role',        // Propriété de l'entité User
        'model'    => App\Entity\Role::class,
        'field'    => 'name',        // Champ du rôle à comparer
    ],

    // Options spécifiques au guard
    'options' => [
        // Pour le guard 'session'
        'timeout' => 1800,           // Durée d'inactivité avant déconnexion (secondes)

        // Pour le guard 'token'
        'secret'     => 'votre-cle-jwt-secrete',
        'expiration' => 3600,
        'algorithm'  => 'HS256',
    ],
];
```

Si `enabled` vaut `false`, l'`AuthManager` est instancié sans guard. Toute méthode nécessitant un guard lèvera une `AuthException`.

La clé `role` est transformée par `AuthManager` en instance de `RoleConfig` (ou `null` si absente/incomplète) avant d'être transmise au guard.

---

## AuthManager

**Fichier :** `AuthManager.php`

Point d'entrée unique pour l'authentification. Lit la configuration depuis `auth.config.php` et délègue au guard approprié.

```php
$auth = $container->get(AuthManager::class);

// Tentative de connexion avec des identifiants
$success = $auth->attempt(['email' => 'user@example.com', 'password' => 'secret']);

// Connexion directe d'un objet utilisateur
$auth->login($userObject);

// Déconnexion
$auth->logout();

// Vérifier si l'utilisateur est connecté
if ($auth->check()) { /* ... */ }

// Obtenir l'utilisateur courant (null si non connecté)
$user = $auth->user();

// Vérifier un rôle
if ($auth->hasRole('admin')) { /* ... */ }

// Générer un token JWT (guard 'token' uniquement)
$token = $auth->generateToken($userObject);
```

**Résolution du guard :**

```php
return match($guardType) {
    'token'   => new TokenGuard(...),
    default   => new SessionGuard(...),
};
```

---

## RoleConfig

**Fichier :** `RoleConfig.php`

DTO qui remplace le tableau associatif `['relation', 'model', 'field']` autrefois dupliqué entre `SessionGuard` et `TokenGuard`. Il représente la configuration des rôles telle que déclarée sous la clé `role` de `auth.config.php`.

```php
class RoleConfig
{
    public function __construct(
        private string $relation, // Propriété de l'entité User pointant vers le rôle
        private string $model,    // Classe (FQCN) de l'entité Role
        private string $field,    // Champ du rôle à comparer avec hasRole()
    ) {}
}
```

Accès via `getRelation()`, `getModel()`, `getField()`. `RoleConfig::fromArray($data)` construit l'instance à partir du tableau de configuration et retourne `null` si l'une des trois clés est absente ou n'est pas une chaîne — c'est ce `null` que les guards interprètent comme « pas de gestion de rôle configurée » dans `hasRole()`.

---

## SessionGuard

**Fichier :** `Guard/SessionGuard.php`

Persiste l'authentification en session PHP.

**Clés de session utilisées :**

| Clé | Contenu |
|-----|---------|
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
    $this->logout(); // Supprime les clés de session
    return false;
}
// Renouvellement automatique de la dernière activité
$this->session->set('_auth_last_activity', time());
```

Le timeout par défaut est **1800 secondes** (30 minutes), configurable via `options.timeout`.

**Vérification des rôles :** `hasRole()` reçoit un `?RoleConfig` en dépendance et retourne directement `false` s'il vaut `null`.

---

## TokenGuard

**Fichier :** `Guard/TokenGuard.php`

Authentifie les requêtes via un **token JWT** transmis dans le header `Authorization: Bearer <token>`. Stateless — aucune donnée n'est stockée côté serveur.

**Extraction du token :**

```php
$header = $this->request->header('Authorization');
// Doit commencer par 'Bearer '
$token = substr($header, 7);
```

**Génération d'un token :**

```php
// Le payload contient uniquement 'sub' => id de l'utilisateur
$token = $auth->generateToken($userObject);
```

**Différences avec `SessionGuard` :**

- `login()` est une méthode vide (stateless).
- `logout()` efface uniquement le payload mis en cache mémoire.
- Pas de stockage côté serveur.

**Récupération de l'utilisateur :** décode le token, extrait le claim `sub`, charge l'entité depuis la base de données via l'`EntityManager`.

**Vérification des rôles :** même logique que `SessionGuard`, basée sur le même `?RoleConfig`.

---

## JwtManager

**Fichier :** `JwtManager.php`

Gère la génération, le décodage et la validation des tokens JWT **sans dépendance externe** (HMAC-SHA256 natif PHP).

### Génération

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

### Décodage et validation

```php
// Décode et vérifie la signature + l'expiration
$payload = $jwt->decode($token);
// ['sub' => 42, 'role' => 'admin', 'iat' => ..., 'exp' => ...]

// Vérification silencieuse (pas d'exception)
$isValid = $jwt->isValid($token); // true | false
```

**Exceptions levées par `decode()` :**

| Situation | Message |
|-----------|---------|
| Format invalide (pas 3 parties) | `Invalid token format.` |
| Signature incorrecte | `Invalid token signature.` |
| Payload non décodable | `Invalid token payload.` |
| Token expiré | `The token has expired.` |

**Sécurité :** La comparaison de signature utilise `hash_equals()` pour prévenir les attaques temporelles.

---

## PasswordManager

**Fichier :** `PasswordManager.php`

Encapsule les fonctions natives PHP de gestion des mots de passe. Algorithme `PASSWORD_DEFAULT` (bcrypt), cost 12.

```php
$pm = $container->get(PasswordManager::class);

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

---

## Extension contrôleur

**Fichier :** `Extension/AuthControllerExtension.php`

Injecte automatiquement deux méthodes dans tous les contrôleurs.

```php
class AuthController extends AbstractController
{
    #[Route('/login', 'POST')]
    public function login(): Response
    {
        $success = $this->auth()->attempt([
            'email'    => $this->getRequest()->body('email'),
            'password' => $this->getRequest()->body('password'),
        ]);

        if (!$success) {
            return $this->jsonError('Identifiants invalides', 401);
        }

        return $this->redirect('/dashboard');
    }

    #[Route('/logout', 'POST')]
    public function logout(): Response
    {
        $this->auth()->logout();
        return $this->redirect('/login');
    }

    #[Route('/register', 'POST')]
    public function register(): Response
    {
        $hash = $this->getPasswordManager()->hash($this->getRequest()->body('password'));
        // ...
    }
}
```

---

## Extension Twig

**Fichier :** `Extension/AuthViewExtension.php`

Expose trois fonctions dans tous les templates Twig :

| Fonction | Description |
|----------|-------------|
| `auth_check()` | `true` si l'utilisateur est connecté |
| `auth_user()` | Objet utilisateur courant (`null` si déconnecté) |
| `auth_has_role(role)` | `true` si l'utilisateur possède le rôle donné |

```twig
{% if auth_check() %}
    <span>Bonjour, {{ auth_user().getName() }}</span>

    {% if auth_has_role('admin') %}
        <a href="/admin">Administration</a>
    {% endif %}

    <a href="/logout">Déconnexion</a>
{% else %}
    <a href="/login">Connexion</a>
{% endif %}
```