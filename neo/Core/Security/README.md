# Security

The `Security` module brings together three complementary subsystems that secure NeoPHP applications:

- **Auth** — session or JWT authentication, role management, password hashing.
- **CSRF** — protection against Cross-Site Request Forgery attacks via session tokens.
- **Middleware** — declarative, attribute-based authorization pipeline for PHP 8, with built-in middlewares and support for custom middlewares.

---

## Module Structure

```
Security/
├── Auth/
│   ├── AuthManager.php                    Entry point for authentication
│   ├── AuthModule.php
│   ├── JwtManager.php                     JWT HMAC-SHA256 with no dependency
│   ├── PasswordManager.php                bcrypt hashing (cost 12)
│   ├── Collector/  AuthCollector
│   ├── Exception/  AuthException, JwtException
│   ├── Extension/  AuthControllerExtension, AuthViewExtension
│   └── Guard/      SessionGuard, TokenGuard
├── Csrf/
│   ├── CsrfManager.php                    Single token per session
│   ├── CsrfTokenManager.php               Named tokens with expiration
│   ├── CsrfModule.php
│   ├── Exception/  CsrfException
│   ├── Extension/  CsrfViewExtension
│   └── Token/      CsrfToken
└── Middleware/
    ├── MiddlewareManager.php              Pipeline orchestrator
    ├── MiddlewareModule.php
    ├── Interface/  MiddlewareInterface
    ├── Attribute/  Middleware, IsGranted
    ├── Default/    Auth, Guest, IsGranted, Role, Csrf, RateLimit, AuthRateLimit
    ├── Exception/  MiddlewareException
    ├── Extension/  MiddlewareControllerExtension
    └── Commands/   MakeMiddlewareCommand
```

---

## Documentation by Component

| Component | Description | README |
|-----------|-------------|--------|
| `Auth` | Session/JWT, roles, bcrypt, JwtManager | [Auth/README.md](Auth/README.md) |
| `Csrf` | Session token, named tokens, Twig `csrf_token()` | [Csrf/README.md](Csrf/README.md) |
| `Middleware` | Attribute pipeline, built-in and custom middlewares | [Middleware/README.md](Middleware/README.md) |

---

## Controller Extensions

| Method | Component |
|---------|-----------|
| `auth()` | Auth — access to `AuthManager` |
| `getPasswordManager()` | Auth — access to `PasswordManager` |
| `getMiddleware()` | Middleware — access to `MiddlewareManager` |

## Twig Extensions

| Function | Component | Description |
|----------|-----------|-------------|
| `auth_check()` | Auth | `true` if the user is logged in |
| `auth_user()` | Auth | Current user object |
| `auth_has_role(role)` | Auth | `true` if the user has the given role |
| `csrf_token(id?)` | Csrf | CSRF token for forms |