# Responses

Le sous-module `Response` couvre les trois types de réponses HTTP et le `ResponseManager` qui les expose via le conteneur et les contrôleurs.

---

## Sommaire

1. [Structure](#structure)
2. [Response](#response)
3. [JsonResponse](#jsonresponse)
4. [RedirectResponse](#redirectresponse)
5. [ResponseManager](#responsemanager)
6. [Extension contrôleur](#extension-contrôleur)

---

---

## Structure

```
Response/
├── ResponseManager.php              # Fabrique de réponses
├── ResponseModule.php               # Enregistrement DI
├── Types/
│   ├── Response.php                 # Réponse HTTP générique
│   ├── JsonResponse.php             # Réponse JSON
│   └── RedirectResponse.php         # Réponse de redirection
└── Extension/
    └── ResponseControllerExtension.php  # Injecte json(), jsonSuccess(), jsonError()
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

### `setHeader` vs `addHeader`

```php
// setHeader : écrase la valeur existante
$response->setHeader('Cache-Control', 'no-cache');

// addHeader : concatène si la clé existe déjà (séparateur : ", ")
$response->addHeader('Cache-Control', 'no-store');
// Résultat : 'no-cache, no-store'
```

### Lecture

```php
$response->getStatusCode();  // int : code HTTP
$response->getHeaders();     // array<string, string> : en-têtes
$response->getContent();     // string : corps brut
```

### Décodage JSON

```php
// Décode le corps JSON en tableau PHP
// Lève HttpClientException si le corps n'est pas un JSON valide ou n'est pas un tableau
$data = $response->toArray();
```

Utilisé principalement avec `HttpClientManager` pour consommer des API JSON.

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

L'encodage lève une `JsonException` si les données ne sont pas sérialisables (`JSON_THROW_ON_ERROR`).

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

## ResponseManager

**Fichier :** `ResponseManager.php`

Fabrique de réponses enregistrée dans le conteneur DI. Expose des raccourcis pour les cas courants.

```php
$manager = $container->get(ResponseManager::class);

// Réponse JSON générique
$manager->json(['key' => 'value'], 200);

// Réponse JSON succès (enveloppe { success: true, data: ... })
$manager->jsonSuccess(['id' => 42]);
$manager->jsonSuccess([], 201);

// Réponse JSON erreur (enveloppe { success: false, error: ... })
$manager->jsonError('Ressource introuvable', 404);
$manager->jsonError('Validation échouée', 422, ['fields' => ['email']]);

// Redirection
$manager->redirect('/dashboard');
$manager->redirect('/new-url', 301);

// Réponse vide à construire manuellement
$response = $manager->make(); // new Response()
```

---

## Extension contrôleur

**Fichier :** `Extension/ResponseControllerExtension.php`

Injecte automatiquement `json()`, `jsonSuccess()` et `jsonError()` dans tous les contrôleurs.

```php
class ApiController extends AbstractController
{
    #[Route('/api/users/{id}', 'GET')]
    public function show(int $id): JsonResponse
    {
        $user = $this->getRepository(User::class)->find($id);

        if (!$user) {
            return $this->jsonError('Utilisateur introuvable', 404);
        }

        return $this->jsonSuccess(['id' => $user->getId(), 'name' => $user->getName()]);
    }
}
```
