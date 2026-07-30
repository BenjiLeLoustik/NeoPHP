# HttpClient — NeoPHP

`Neo\Core\Http\HttpClient\HttpClientManager` est un client HTTP basé sur cURL pour effectuer des requêtes sortantes. Il retourne un objet `Response` standard, ce qui permet d'utiliser `toArray()` pour décoder directement les réponses JSON.

---

## Sommaire

1. [Structure](#structure)
2. [Utilisation de base](#utilisation-de-base)
3. [Options disponibles](#options-disponibles)
4. [Corps de la requête](#corps-de-la-requête)
5. [Authentification](#authentification)
6. [Lecture de la réponse](#lecture-de-la-réponse)
7. [Options par défaut](#options-par-défaut)
8. [HttpClientInterface](#httpclientinterface)
9. [HttpClientException](#httpclientexception)

---

## Structure

```
HttpClient/
├── HttpClientManager.php               # Client HTTP cURL
├── HttpClientModule.php                # Enregistrement DI
├── Interface/
│   └── HttpClientInterface.php         # Contrat du client HTTP
└── Exception/
    └── HttpClientException.php         # Erreur réseau ou réponse invalide
```

---

## Utilisation de base

```php
$client = $container->get(HttpClientManager::class);

// Requête GET
$response = $client->request('GET', 'https://api.example.com/users');

// Requête POST avec corps JSON
$response = $client->request('POST', 'https://api.example.com/users', [
    'json' => ['name' => 'Alice', 'email' => 'alice@example.com'],
]);

// Accéder à la réponse
$response->getStatusCode();  // 201
$data = $response->toArray(); // ['id' => 42, 'name' => 'Alice', ...]
```

---

## Options disponibles

| Clé | Type | Défaut | Description |
|-----|------|--------|-------------|
| `base_uri` | `string` | — | Préfixé aux URLs relatives |
| `query` | `array` | — | Paramètres ajoutés à la query string |
| `headers` | `array` | `[]` | En-têtes de la requête |
| `bearer` | `string` | — | Token Bearer (ajoute `Authorization: Bearer ...`) |
| `json` | `array\|object` | — | Corps encodé en JSON (`Content-Type: application/json`) |
| `body` | `string\|array` | — | Corps brut (array = form-encodé) |
| `auth_basic` | `string\|array` | — | Authentification Basic (`"user:pass"` ou `['user', 'pass']`) |
| `timeout` | `float` | `30.0` | Timeout en secondes |
| `max_redirects` | `int` | `20` | Nombre max de redirections (0 = désactivé) |

---

## Corps de la requête

### JSON

```php
$response = $client->request('POST', '/api/articles', [
    'json' => [
        'title'   => 'Mon article',
        'content' => 'Contenu...',
    ],
]);
// Content-Type: application/json ajouté automatiquement
```

### Formulaire (form-urlencoded)

```php
$response = $client->request('POST', '/login', [
    'body' => ['username' => 'alice', 'password' => 'secret'],
]);
// Content-Type: application/x-www-form-urlencoded ajouté automatiquement
```

### Corps brut

```php
$response = $client->request('PUT', '/upload', [
    'headers' => ['Content-Type' => 'text/plain'],
    'body'    => 'Contenu brut',
]);
```

---

## Authentification

### Bearer Token

```php
$response = $client->request('GET', '/api/me', [
    'bearer' => $jwtToken,
]);
// Ajoute : Authorization: Bearer <token>
```

### Basic Auth

```php
// Sous forme de chaîne
$response = $client->request('GET', '/protected', [
    'auth_basic' => 'user:password',
]);

// Sous forme de tableau
$response = $client->request('GET', '/protected', [
    'auth_basic' => ['user', 'password'],
]);
```

---

## Lecture de la réponse

`request()` retourne un objet `Response` standard enrichi de méthodes de lecture :

```php
$response = $client->request('GET', 'https://api.example.com/status');

// Code HTTP
$response->getStatusCode();   // 200

// En-têtes de la réponse (noms en minuscules)
$response->getHeaders();      // ['content-type' => 'application/json', ...]

// Corps brut
$response->getContent();      // '{"status":"ok"}'

// Décodage JSON (lève HttpClientException si invalide)
$data = $response->toArray(); // ['status' => 'ok']
```

---

## Options par défaut

Pour un client qui effectue plusieurs requêtes vers la même API, définissez des options partagées dans le constructeur :

```php
$client = new HttpClientManager([
    'base_uri' => 'https://api.example.com',
    'bearer'   => $apiToken,
    'timeout'  => 10.0,
    'headers'  => [
        'Accept' => 'application/json',
    ],
]);

// Les options passées à request() écrasent les defaults (array_replace)
$users    = $client->request('GET', '/users')->toArray();
$articles = $client->request('GET', '/articles', ['timeout' => 5.0])->toArray();
```

---

## HttpClientInterface

**Fichier :** `Interface/HttpClientInterface.php`

```php
interface HttpClientInterface
{
    /**
     * @param array<string, mixed> $options
     * @throws HttpClientException
     */
    public function request(string $method, string $url, array $options = []): Response;
}
```

`HttpClientModule` enregistre `HttpClientManager` comme implémentation de `HttpClientInterface` dans le conteneur :

```php
$client = $container->get(HttpClientInterface::class);
```

---

## HttpClientException

**Fichier :** `Exception/HttpClientException.php`

Étend `FrameworkException`. Levée dans trois cas :

| Code | Cause |
|------|-------|
| `500` | Erreur cURL (réseau, DNS, timeout) |
| `500` | Corps de la requête JSON non encodable |
| `500` | `toArray()` sur une réponse non-JSON ou non-tableau |
