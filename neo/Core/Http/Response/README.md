# Responses

The `Response` sub-module covers the three types of HTTP responses and the `ResponseManager` that exposes them through the container and controllers.

---

## Summary

1. [Structure](#structure)
2. [Response](#response)
3. [JsonResponse](#jsonresponse)
4. [RedirectResponse](#redirectresponse)
5. [ResponseManager](#responsemanager)
6. [Controller Extension](#controller-extension)

---

---

## Structure

```
Response/
├── ResponseManager.php              # Response factory
├── ResponseModule.php               # DI registration
├── Types/
│   ├── Response.php                 # Generic HTTP response
│   ├── JsonResponse.php             # JSON response
│   └── RedirectResponse.php         # Redirect response
└── Extension/
    └── ResponseControllerExtension.php  # Injects json(), jsonSuccess(), jsonError()
```

---

## Response

`Neo\Core\Http\Response\Types\Response` is the base class for every response.

### Construction and chaining

```php
$response = new Response();
$response
    ->setStatusCode(200)
    ->setHeader('X-Frame-Options', 'DENY')
    ->setHeader('Content-Type', 'text/html; charset=utf-8')
    ->setContent('<h1>Hello</h1>');
```

### `setHeader` vs `addHeader`

```php
// setHeader: overwrites the existing value
$response->setHeader('Cache-Control', 'no-cache');

// addHeader: concatenates if the key already exists (separator: ", ")
$response->addHeader('Cache-Control', 'no-store');
// Result: 'no-cache, no-store'
```

### Reading

```php
$response->getStatusCode();  // int: HTTP status code
$response->getHeaders();     // array<string, string>: headers
$response->getContent();     // string: raw body
```

### JSON decoding

```php
// Decodes the JSON body into a PHP array
// Throws HttpClientException if the body is not valid JSON or is not an array
$data = $response->toArray();
```

Mainly used with `HttpClientManager` to consume JSON APIs.

### Sending

```php
$response->send();
// Calls http_response_code(), header() for each header, then echoes the content
```

---

## JsonResponse

`JsonResponse` extends `Response`. Automatic JSON encoding with the `Content-Type: application/json; charset=utf-8` header.

```php
// Array
$response = new JsonResponse(['status' => 'ok', 'user' => ['id' => 1]]);

// Object
$response = new JsonResponse($userObject);

// With a custom HTTP code
$response = new JsonResponse(['error' => 'Not found'], 404);

$response->send();
```

Encoding throws a `JsonException` if the data is not serializable (`JSON_THROW_ON_ERROR`).

---

## RedirectResponse

`RedirectResponse` extends `Response`. Sets the HTTP status code and the `Location` header.

```php
// Temporary redirect (302 by default)
$response = new RedirectResponse('/dashboard');

// Permanent redirect
$response = new RedirectResponse('/new-url', 301);

// Redirect after a form submission (Post/Redirect/Get)
$response = new RedirectResponse('/form?success=1', 303);

$response->send();
```

---

## ResponseManager

**File:** `ResponseManager.php`

Response factory registered in the DI container. Exposes shortcuts for common cases.

```php
$manager = $container->get(ResponseManager::class);

// Generic JSON response
$manager->json(['key' => 'value'], 200);

// Successful JSON response (wraps { success: true, data: ... })
$manager->jsonSuccess(['id' => 42]);
$manager->jsonSuccess([], 201);

// Error JSON response (wraps { success: false, error: ... })
$manager->jsonError('Resource not found', 404);
$manager->jsonError('Validation failed', 422, ['fields' => ['email']]);

// Redirect
$manager->redirect('/dashboard');
$manager->redirect('/new-url', 301);

// Empty response to build manually
$response = $manager->make(); // new Response()
```

---

## Controller Extension

**File:** `Extension/ResponseControllerExtension.php`

Automatically injects `json()`, `jsonSuccess()`, and `jsonError()` into every controller.

```php
class ApiController extends AbstractController
{
    #[Route('/api/users/{id}', 'GET')]
    public function show(int $id): JsonResponse
    {
        $user = $this->getRepository(User::class)->find($id);

        if (!$user) {
            return $this->jsonError('User not found', 404);
        }

        return $this->jsonSuccess(['id' => $user->getId(), 'name' => $user->getName()]);
    }
}
```