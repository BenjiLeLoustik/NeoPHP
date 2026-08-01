# HttpClient

`Neo\Core\Http\HttpClient\HttpClientManager` is a cURL-based HTTP client for making outbound requests. It returns a standard `Response` object, which allows using `toArray()` to directly decode JSON responses.

---

## Summary

1. [Structure](#structure)
2. [Basic Usage](#basic-usage)
3. [Available Options](#available-options)
4. [Request Body](#request-body)
5. [Authentication](#authentication)
6. [Reading the Response](#reading-the-response)
7. [Default Options](#default-options)
8. [HttpClientInterface](#httpclientinterface)
9. [HttpClientException](#httpclientexception)

---

## Structure

```
HttpClient/
├── HttpClientManager.php               # cURL-based HTTP client
├── HttpClientModule.php                # DI registration
├── Interface/
│   └── HttpClientInterface.php         # Contract for the HTTP client
└── Exception/
    └── HttpClientException.php         # Network error or invalid response
```

---

## Basic Usage

```php
$client = $container->get(HttpClientManager::class);

// GET request
$response = $client->request('GET', 'https://api.example.com/users');

// POST request with a JSON body
$response = $client->request('POST', 'https://api.example.com/users', [
    'json' => ['name' => 'Alice', 'email' => 'alice@example.com'],
]);

// Access the response
$response->getStatusCode();  // 201
$data = $response->toArray(); // ['id' => 42, 'name' => 'Alice', ...]
```

---

## Available Options

| Key | Type | Default | Description |
|-----|------|--------|-------------|
| `base_uri` | `string` | — | Prefixed to relative URLs |
| `query` | `array` | — | Parameters added to the query string |
| `headers` | `array` | `[]` | Request headers |
| `bearer` | `string` | — | Bearer token (adds `Authorization: Bearer ...`) |
| `json` | `array\|object` | — | JSON-encoded body (`Content-Type: application/json`) |
| `body` | `string\|array` | — | Raw body (array = form-encoded) |
| `auth_basic` | `string\|array` | — | Basic authentication (`"user:pass"` or `['user', 'pass']`) |
| `timeout` | `float` | `30.0` | Timeout in seconds |
| `max_redirects` | `int` | `20` | Max number of redirects (0 = disabled) |

---

## Request Body

### JSON

```php
$response = $client->request('POST', '/api/articles', [
    'json' => [
        'title'   => 'My article',
        'content' => 'Content...',
    ],
]);
// Content-Type: application/json added automatically
```

### Form (form-urlencoded)

```php
$response = $client->request('POST', '/login', [
    'body' => ['username' => 'alice', 'password' => 'secret'],
]);
// Content-Type: application/x-www-form-urlencoded added automatically
```

### Raw body

```php
$response = $client->request('PUT', '/upload', [
    'headers' => ['Content-Type' => 'text/plain'],
    'body'    => 'Raw content',
]);
```

---

## Authentication

### Bearer Token

```php
$response = $client->request('GET', '/api/me', [
    'bearer' => $jwtToken,
]);
// Adds: Authorization: Bearer <token>
```

### Basic Auth

```php
// As a string
$response = $client->request('GET', '/protected', [
    'auth_basic' => 'user:password',
]);

// As an array
$response = $client->request('GET', '/protected', [
    'auth_basic' => ['user', 'password'],
]);
```

---

## Reading the Response

`request()` returns a standard `Response` object enriched with reading methods:

```php
$response = $client->request('GET', 'https://api.example.com/status');

// HTTP status code
$response->getStatusCode();   // 200

// Response headers (lowercase names)
$response->getHeaders();      // ['content-type' => 'application/json', ...]

// Raw body
$response->getContent();      // '{"status":"ok"}'

// JSON decoding (throws HttpClientException if invalid)
$data = $response->toArray(); // ['status' => 'ok']
```

---

## Default Options

For a client that makes multiple requests to the same API, define shared options in the constructor:

```php
$client = new HttpClientManager([
    'base_uri' => 'https://api.example.com',
    'bearer'   => $apiToken,
    'timeout'  => 10.0,
    'headers'  => [
        'Accept' => 'application/json',
    ],
]);

// Options passed to request() override the defaults (array_replace)
$users    = $client->request('GET', '/users')->toArray();
$articles = $client->request('GET', '/articles', ['timeout' => 5.0])->toArray();
```

---

## HttpClientInterface

**File:** `Interface/HttpClientInterface.php`

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

`HttpClientModule` registers `HttpClientManager` as the implementation of `HttpClientInterface` in the container:

```php
$client = $container->get(HttpClientInterface::class);
```

---

## HttpClientException

**File:** `Exception/HttpClientException.php`

Extends `FrameworkException`. Thrown in three cases:

| Code | Cause |
|------|-------|
| `500` | cURL error (network, DNS, timeout) |
| `500` | Request body cannot be JSON-encoded |
| `500` | `toArray()` called on a non-JSON or non-array response |