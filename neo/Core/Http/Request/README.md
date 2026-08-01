# Request

`Neo\Core\Http\Request\Request` est l'objet immuable représentant la requête HTTP entrante. Le constructeur est privé ; la création se fait exclusivement via des méthodes statiques.

---

## Sommaire

1. [Structure](#structure)
2. [Construction](#construction)
3. [Lecture des données](#lecture-des-données)
4. [Contenu brut](#contenu-brut)
5. [Fichiers uploadés](#fichiers-uploadés)
6. [Suivi de l'URL précédente](#suivi-de-lurl-précédente)
7. [Limite de taille](#limite-de-taille)

---

## Structure

```
Request/
├── Request.php               # Requête HTTP entrante (immuable)
└── Collector/
    └── RequestCollector.php  # Collecteur Profiler
```

---

## Construction

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

---

## Lecture des données

```php
// Méthode HTTP (toujours en majuscules)
$request->getMethod();          // 'GET', 'POST', 'PUT', ...

// Chemin URL (sanitisé, sans trailing slash)
$request->getPath();            // '/api/users'

// URL complète avec query string
$request->getFullUrl();         // '/api/users?page=1'

// Paramètres GET
$request->query('page', 1);    // valeur ou défaut
$request->allQuery();           // array complet

// Corps de la requête (POST ou JSON décodé)
$request->body('name');         // valeur ou null
$request->body();               // array complet
$request->allBody();            // alias de body()

// En-têtes (nommés en PascalCase-With-Dash)
$request->header('Content-Type');         // 'application/json'
$request->header('Authorization', null);  // valeur ou défaut
$request->headers();                      // array complet

// Données $_SERVER
$request->server();
$request->getServer();

// IP du client (gère Cloudflare, X-Real-IP, X-Forwarded-For)
$request->getIp();              // '93.184.216.34' ou null

// User-Agent
$request->getUserAgent();
```

---

## Contenu brut

Relit `php://input` et décode le JSON si `Content-Type: application/json` :

```php
$content = $request->getContent(); // string ou array
```

---

## Fichiers uploadés

```php
// Accéder à un fichier par son nom de champ
$file = $request->file('avatar');  // ?UploadedFile

// Tous les fichiers
$files = $request->allFiles();     // array<string, array>
```

Voir [File/README.md](../File/README.md) pour l'upload et la validation des fichiers.

---

## Suivi de l'URL précédente

```php
// Active le tracking (appel automatique par le framework)
$request->enablePreviousUrlTracking($session);

// Récupérer l'URL précédente (uniquement pour les requêtes GET non-/api)
$previous = $request->getPreviousUrl('/'); // URL ou fallback
```

---

## Limite de taille

La taille maximale des corps de requête est limitée à **8 Mo** (`INPUT_MAX_SIZE = 8 * 1024 * 1024`). Au-delà, une réponse HTTP 413 est envoyée immédiatement.
