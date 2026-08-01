# Request

`Neo\Core\Http\Request\Request` is the immutable object representing the incoming HTTP request. The constructor is private; instances are created exclusively via static methods.

---

## Summary

1. [Structure](#structure)
2. [Construction](#construction)
3. [Reading Data](#reading-data)
4. [Raw Content](#raw-content)
5. [Uploaded Files](#uploaded-files)
6. [Previous URL Tracking](#previous-url-tracking)
7. [Size Limit](#size-limit)

---

## Structure

```
Request/
├── Request.php               # Incoming HTTP request (immutable)
└── Collector/
    └── RequestCollector.php  # Profiler collector
```

---

## Construction

```php
// From PHP superglobals ($_SERVER, $_GET, $_POST, $_FILES)
$request = Request::fromGlobals();

// From an array (tests, CLI)
$request = Request::fromArray(
    method: 'POST',
    path: '/api/users',
    query: ['page' => '1'],
    body: ['name' => 'Alice'],
    headers: ['Content-Type' => 'application/json'],
    server: []
);

// Empty request for CLI context
$request = Request::createEmpty();
```

---

## Reading Data

```php
// HTTP method (always uppercase)
$request->getMethod();          // 'GET', 'POST', 'PUT', ...

// URL path (sanitized, no trailing slash)
$request->getPath();            // '/api/users'

// Full URL with query string
$request->getFullUrl();         // '/api/users?page=1'

// GET parameters
$request->query('page', 1);    // value or default
$request->allQuery();           // full array

// Request body (POST or decoded JSON)
$request->body('name');         // value or null
$request->body();               // full array
$request->allBody();            // alias for body()

// Headers (named in PascalCase-With-Dash)
$request->header('Content-Type');         // 'application/json'
$request->header('Authorization', null);  // value or default
$request->headers();                      // full array

// $_SERVER data
$request->server();
$request->getServer();

// Client IP (handles Cloudflare, X-Real-IP, X-Forwarded-For)
$request->getIp();              // '93.184.216.34' or null

// User-Agent
$request->getUserAgent();
```

---

## Raw Content

Re-reads `php://input` and decodes JSON if `Content-Type: application/json`:

```php
$content = $request->getContent(); // string or array
```

---

## Uploaded Files

```php
// Access a file by its field name
$file = $request->file('avatar');  // ?UploadedFile

// Every file
$files = $request->allFiles();     // array<string, array>
```

See [File/README.md](../File/README.md) for file upload and validation.

---

## Previous URL Tracking

```php
// Enables tracking (called automatically by the framework)
$request->enablePreviousUrlTracking($session);

// Retrieve the previous URL (only for non-/api GET requests)
$previous = $request->getPreviousUrl('/'); // URL or fallback
```

---

## Size Limit

The maximum size of request bodies is limited to **8 MB** (`INPUT_MAX_SIZE = 8 * 1024 * 1024`). Beyond that, an HTTP 413 response is sent immediately.