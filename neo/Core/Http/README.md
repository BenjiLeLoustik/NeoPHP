# Module Http — Requête, Réponse, Session, Flash, Cookie, Upload

Le module `Http` couvre l'intégralité du cycle requête/réponse de NeoPHP : abstraction de la requête entrante, types de réponses HTTP, gestion de la session PHP, messages flash, cookies, et upload de fichiers.

---

## Structure du module

```
Http/
├── Request/
│   └── Request.php                  Requête HTTP entrante
├── Response/
│   └── Types/
│       ├── Response.php             Réponse HTTP générique
│       ├── JsonResponse.php         Réponse JSON
│       └── RedirectResponse.php     Réponse de redirection
├── Client/
│   ├── Session/
│   │   └── Session.php              Gestion de la session PHP
│   ├── Flash/
│   │   └── Flash.php                Messages flash (session éphémère)
│   └── Cookie/
│       └── Cookie.php               Gestion des cookies
└── File/
    └── UploaderManager.php          Gestion des uploads de fichiers
```

---

## Request

`Neo\Core\Http\Request\Request` est l'objet immuable représentant la requête HTTP courante. Le constructeur est privé ; la création se fait via des méthodes statiques.

### Construction

```php
// À partir des superglobales PHP ($_SERVER, $_GET, $_POST, $_FILES)
$request = Request::fromGlobals();

// À partir d'un tableau (tests, CLI)
$request = Request::fromArray(
    method: 'POST',
    path: '/api/users',
    query: ['page' => '1'],
    body: ['name' => 'Alice'],
    headers: ['Content-Type' => 'application/json'],
    server: []
);

// Requête vide pour contexte CLI
$request = Request::createEmpty();
```

### Lecture des données

```php
// Méthode HTTP (toujours en majuscules)
$request->getMethod();         // 'GET', 'POST', 'PUT', ...

// Chemin URL (sanitisé, sans trailing slash)
$request->getPath();           // '/api/users'

// URL complète avec query string
$request->getFullUrl();        // '/api/users?page=1'

// Paramètres GET
$request->query('page', 1);    // valeur ou défaut
$request->allQuery();          // array complet

// Corps de la requête (POST ou JSON décodé)
$request->body('name');        // valeur ou null
$request->body();              // array complet
$request->allBody();           // alias

// En-têtes (nommés en PascalCase-With-Dash)
$request->header('Content-Type');        // 'application/json'
$request->header('Authorization', null); // valeur ou défaut
$request->headers();                     // array complet

// Données $_SERVER
$request->server();
$request->getServer();

// IP du client (gère les proxies Cloudflare, X-Real-IP, X-Forwarded-For)
$request->getIp();             // '93.184.216.34' ou null

// User-Agent
$request->getUserAgent();
```

### Contenu brut

```php
// Relit php://input et décode le JSON si Content-Type: application/json
$content = $request->getContent(); // string ou array
```

### Limite de taille

La taille maximale des corps de requête est limitée à **8 Mo** (`INPUT_MAX_SIZE = 8 * 1024 * 1024`). Au-delà, la réponse HTTP 413 est envoyée immédiatement.

### Fichiers uploadés

```php
// Accéder à un fichier par son nom de champ
$file = $request->file('avatar');  // ?UploadedFile

// Tous les fichiers
$files = $request->allFiles();     // array<string, array>
```

### Suivi de l'URL précédente

```php
// Active le tracking (appel automatique par le framework)
$request->enablePreviousUrlTracking($session);

// Récupérer l'URL précédente (uniquement pour les requêtes GET non-/api)
$previous = $request->getPreviousUrl('/'); // URL ou fallback
```

---

## Response

`Neo\Core\Http\Response\Types\Response` est la classe de base pour toutes les réponses.

### Construction et chaînage

```php
$response = new Response();
$response
    ->setStatusCode(200)
    ->setHeader('X-Frame-Options', 'DENY')
    ->setHeader('Content-Type', 'text/html; charset=utf-8')
    ->setContent('<h1>Bonjour</h1>');
```

### `addHeader` vs `setHeader`

```php
// setHeader : écrase la valeur existante
$response->setHeader('Cache-Control', 'no-cache');

// addHeader : concatène si la clé existe déjà (séparateur : ", ")
$response->addHeader('Cache-Control', 'no-store');
// Résultat : 'no-cache, no-store'
```

### Envoi

```php
$response->send();
// Appelle http_response_code(), header() pour chaque en-tête, puis echo du contenu
```

---

## JsonResponse

`JsonResponse` étend `Response`. Encodage JSON automatique avec l'en-tête `Content-Type: application/json; charset=utf-8`.

```php
// Tableau
$response = new JsonResponse(['status' => 'ok', 'user' => ['id' => 1]]);

// Objet
$response = new JsonResponse($userObject);

// Avec code HTTP personnalisé
$response = new JsonResponse(['error' => 'Not found'], 404);

$response->send();
```

L'encodage lève une `JsonException` si les données ne sont pas sérialisables.

---

## RedirectResponse

`RedirectResponse` étend `Response`. Positionne le code HTTP et l'en-tête `Location`.

```php
// Redirection temporaire (302 par défaut)
$response = new RedirectResponse('/dashboard');

// Redirection permanente
$response = new RedirectResponse('/new-url', 301);

// Redirection après formulaire (Post/Redirect/Get)
$response = new RedirectResponse('/form?success=1', 303);

$response->send();
```

---

## Session

`Neo\Core\Http\Client\Session\Session` encapsule la session PHP native avec une configuration centralisée.

La session est configurée depuis `config/session.php`, clé `session` :

```php
// config/session.php (exemple)
return [
    'session' => [
        'enabled'   => true,
        'name'      => 'neo_session',
        'lifetime'  => 7200,
        'secure'    => true,
        'http_only' => true,
        'same_site' => 'Lax',
        'storage'   => [
            'enabled' => true,
            'handler' => 'files',
        ],
    ],
];
```

Les fichiers de session sont stockés dans `storage/var/cache/session/`.

### Utilisation

```php
$session->set('user_id', 42);
$session->get('user_id');         // 42
$session->get('missing', 'def'); // 'def'
$session->has('user_id');        // true
$session->remove('user_id');

$session->all();     // array complet de $_SESSION
$session->clear();   // vide $_SESSION

$session->regenerate(); // Regénère l'ID de session (après login)
$session->destroy();    // Détruit la session
```

En contexte CLI (`PHP_SAPI === 'cli'`), toutes les méthodes sont des no-ops silencieux.

---

## Flash

`Neo\Core\Http\Client\Flash\Flash` gère les messages éphémères stockés en session, consommés à la prochaine lecture.

Configuré depuis `config/session.php`, clé `flash` :

```php
'flash' => [
    'session_key' => '_flash',
    'auto_expire' => true,      // vide les messages après lecture
    'types'       => ['success', 'error', 'warning', 'info'],
],
```

### Ajouter un message

```php
$flash->add('success', 'Votre profil a été mis à jour.');
$flash->add('error', 'Une erreur est survenue.');
```

Le type doit faire partie des types configurés, sinon une `FrameworkException` est levée.

### Lire les messages

```php
// Récupère tous les messages (et les vide si auto_expire = true)
$messages = $flash->getAll();
// [['type' => 'success', 'message' => 'Votre profil ...'], ...]

// Vérifier s'il y a des messages
if ($flash->has()) { /* ... */ }
```

### Rendu HTML

```php
echo $flash->render();
// <span class='flash-message success'>Votre profil a été mis à jour.</span>
// <span class='flash-message error'>Une erreur est survenue.</span>
```

Les valeurs sont passées par `htmlspecialchars()` pour prévenir les XSS.

---

## Cookie

`Neo\Core\Http\Client\Cookie\Cookie` encapsule la gestion des cookies PHP avec préfixage automatique.

Configuré depuis `config/session.php`, clé `cookie` :

```php
'cookie' => [
    'prefix'    => 'neo_',
    'lifetime'  => 2592000,  // 30 jours
    'path'      => '/',
    'domain'    => '',
    'secure'    => true,
    'http_only' => true,
    'same_site' => 'Lax',
],
```

Tous les noms de cookies sont automatiquement préfixés (ex. : `user_theme` → `neo_user_theme`).

### Utilisation

```php
// Écrire un cookie (valeurs de config par défaut)
$cookie->set('user_theme', 'dark');

// Avec paramètres personnalisés
$cookie->set(
    name: 'remember_token',
    value: $token,
    expire: time() + 86400,   // expire dans 1 jour
    path: '/',
    domain: 'example.com',
    secure: true,
    httpOnly: true
);

// Lire
$theme = $cookie->get('user_theme', 'light'); // valeur ou défaut

// Vérifier l'existence
$cookie->has('user_theme'); // true/false

// Supprimer (expire dans le passé)
$cookie->remove('user_theme');

// Tous les cookies bruts (non filtrés par préfixe)
$cookie->all(); // $_COOKIE complet
```

---

## UploaderManager

`Neo\Core\Http\File\UploaderManager` gère l'upload sécurisé de fichiers vers le dossier `assetsPath`.

### Utilisation

```php
$uploader = $container->get(UploaderManager::class);

$file = $request->file('avatar'); // UploadedFile

$finalName = $uploader->upload(
    file: $file,
    name: 'avatar_' . $userId,           // nom souhaité (sans extension)
    allowedExtensions: ['jpg', 'png', 'webp'],
    directory: 'uploads/avatars'          // relatif à assetsPath
);
// Retourne : 'avatar_42.jpg' ou 'avatar_42_1722172800.jpg' si collision
```

### Sécurité

Les extensions suivantes sont **toujours interdites**, quelle que soit la liste `allowedExtensions` :

```
php, phtml, exe, sh, js
```

L'ordre de vérification est :
1. Le fichier est valide (`$file->isValid()`)
2. L'extension n'est pas dans la liste interdite
3. L'extension est dans la liste autorisée (si non vide)

En cas de nom de fichier déjà existant dans le dossier de destination, un suffixe `_<timestamp>` est ajouté automatiquement.

### Exceptions

| Titre | Cause |
|---|---|
| Invalid File | `isValid()` retourne `false` |
| Forbidden File Type | Extension dans la liste noire |
| Extension Not Allowed | Extension absente de la liste blanche |
| Upload Failed | `move_uploaded_file()` a échoué |
