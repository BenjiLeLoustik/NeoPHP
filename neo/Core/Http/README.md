# HTTP Request

The `Http` module covers the entire request/response cycle of NeoPHP: incoming request abstraction, HTTP response types, session management, flash messages, cookies, and file uploads.

---

## Module Structure

```
Http/
├── Request/
│   ├── Request.php                          Incoming HTTP request (immutable)
│   └── Collector/RequestCollector.php       Profiler collector
├── Response/
│   ├── ResponseManager.php                  Response factory
│   ├── ResponseModule.php
│   ├── Types/
│   │   ├── Response.php                     HTTP response (getters + toArray())
│   │   ├── JsonResponse.php                 JSON response
│   │   └── RedirectResponse.php             Redirect response
│   └── Extension/ResponseControllerExtension.php
├── HttpClient/
│   ├── HttpClientManager.php                Outgoing cURL HTTP client
│   ├── HttpClientModule.php
│   ├── Interface/HttpClientInterface.php
│   └── Exception/HttpClientException.php
├── Client/
│   ├── ClientManager.php
│   ├── ClientModule.php
│   ├── Session/
│   │   ├── Session.php                      Native PHP session
│   │   └── Extension/SessionControllerExtension.php
│   ├── Flash/
│   │   ├── Flash.php                        Ephemeral flash messages
│   │   ├── Extension/FlashControllerExtension.php
│   │   └── Extension/FlashViewExtension.php
│   └── Cookie/
│       ├── Cookie.php                       Cookies with automatic prefixing
│       └── Extension/CookieControllerExtension.php
└── File/
    ├── UploaderManager.php                  Secure file uploads
    ├── UploaderModule.php
    ├── Model/UploadedFile.php
    ├── Exception/
    └── Extension/UploaderControllerExtension.php
```

---

## Documentation by Component

| Component | Description | README |
|-----------|-------------|--------|
| `Request` | Incoming HTTP request, reading data, files, IP | [Request/README.md](Request/README.md) |
| `Response` | HTTP responses, JSON, redirect, getters, `toArray()` | [Response/README.md](Response/README.md) |
| `HttpClient` | Outgoing cURL HTTP client, options, auth, `toArray()` | [HttpClient/README.md](HttpClient/README.md) |
| `Session` | Native PHP session, configuration, methods, CLI no-op | [Client/Session/README.md](Client/Session/README.md) |
| `Flash` | Session flash messages, HTML rendering, Twig | [Client/Flash/README.md](Client/Flash/README.md) |
| `Cookie` | Prefixed cookies, configuration, read/write | [Client/Cookie/README.md](Client/Cookie/README.md) |
| `File` | Secure upload, validation, extension blacklist | [File/README.md](File/README.md) |

---

## Controller Extensions

Each component automatically injects its methods into `AbstractController`:

| Method | Component |
|---------|-----------|
| `getSession()` | Session |
| `getFlash()` | Flash |
| `getCookie()` | Cookie |
| `json()` / `jsonSuccess()` / `jsonError()` | Response |
| `upload()` | File |

## Twig Function

| Function | Component | Description |
|----------|-----------|-------------|
| `flashes()` | Flash | HTML rendering of every pending flash message |