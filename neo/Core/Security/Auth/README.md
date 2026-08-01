# Auth

The `Auth` submodule handles user authentication via PHP session or JWT token, role verification, and password hashing.

---

## Summary

1. [Structure](#structure)
2. [Configuration](#configuration)
3. [AuthManager](#authmanager)
4. [RoleConfig](#roleconfig)
5. [SessionGuard](#sessionguard)
6. [TokenGuard](#tokenguard)
7. [JwtManager](#jwtmanager)
8. [PasswordManager](#passwordmanager)
9. [Controller Extension](#controller-extension)
10. [Twig Extension](#twig-extension)

---

## Structure

```
Auth/
├── AuthManager.php                     # Authentication entry point
├── AuthModule.php                      # DI registration
├── JwtManager.php                      # JWT generation/validation (HMAC-SHA256)
├── PasswordManager.php                 # Bcrypt hashing
├── RoleConfig.php                      # Role configuration DTO, shared between guards
├── Collector/
│   └── AuthCollector.php              # Profiler collector
├── Exception/
│   ├── AuthException.php              # Authentication error
│   └── JwtException.php               # JWT validation error
├── Extension/
│   ├── AuthControllerExtension.php    # Injects auth() and getPasswordManager()
│   └── AuthViewExtension.php          # auth_* Twig functions
└── Guard/
    ├── Interface/
    │   └── GuardInterface.php
    ├── SessionGuard.php               # Session-based authentication
    └── TokenGuard.php                 # JWT-based authentication
```

---

## Configuration

**File:** `src/<Project>/Config/auth.config.php`

```php
return [
    'enabled'    => true,

    // Guard used: 'session' or 'token'
    'guard'      => 'session',

    // User model class (FQCN)
    'model'      => App\Entity\User::class,

    // Field used as the login identifier
    'identifier' => 'email',

    // Password field
    'password'   => 'password',

    // Role configuration (optional)
    'role' => [
        'relation' => 'role',        // Property of the User entity
        'model'    => App\Entity\Role::class,
        'field'    => 'name',        // Role field to compare
    ],

    // Guard-specific options
    'options' => [
        // For the 'session' guard
        'timeout' => 1800,           // Inactivity duration before logout (seconds)

        // For the 'token' guard
        'secret'     => 'your-jwt-secret-key',
        'expiration' => 3600,
        'algorithm'  => 'HS256',
    ],
];
```

If `enabled` is `false`, `AuthManager` is instantiated without a guard. Any method requiring a guard will throw an `AuthException`.

The `role` key is transformed by `AuthManager` into a `RoleConfig` instance (or `null` if absent/incomplete) before being passed to the guard.

---

## AuthManager

**File:** `AuthManager.php`

Single entry point for authentication. Reads the configuration from `auth.config.php` and delegates to the appropriate guard.

```php
$auth = $container->get(AuthManager::class);

// Login attempt with credentials
$success = $auth->attempt(['email' => 'user@example.com', 'password' => 'secret']);

// Direct login of a user object
$auth->login($userObject);

// Logout
$auth->logout();

// Check whether the user is logged in
if ($auth->check()) { /* ... */ }

// Get the current user (null if not logged in)
$user = $auth->user();

// Check a role
if ($auth->hasRole('admin')) { /* ... */ }

// Generate a JWT token ('token' guard only)
$token = $auth->generateToken($userObject);
```

**Guard resolution:**

```php
return match($guardType) {
    'token'   => new TokenGuard(...),
    default   => new SessionGuard(...),
};
```

---

## RoleConfig

**File:** `RoleConfig.php`

DTO that replaces the associative array `['relation', 'model', 'field']` formerly duplicated between `SessionGuard` and `TokenGuard`. It represents the role configuration as declared under the `role` key of `auth.config.php`.

```php
class RoleConfig
{
    public function __construct(
        private string $relation, // Property of the User entity pointing to the role
        private string $model,    // Class (FQCN) of the Role entity
        private string $field,    // Role field to compare with hasRole()
    ) {}
}
```

Accessed via `getRelation()`, `getModel()`, `getField()`. `RoleConfig::fromArray($data)` builds the instance from the configuration array and returns `null` if any of the three keys is absent or not a string — this `null` is what guards interpret as "no role management configured" in `hasRole()`.

---

## SessionGuard

**File:** `Guard/SessionGuard.php`

Persists authentication in the PHP session.

**Session keys used:**

| Key | Content |
|-----|---------|
| `_auth_user_id` | Primary identifier of the user |
| `_auth_last_activity` | Unix timestamp of the last activity |

**How `attempt()` works:**

1. Checks that the credentials contain the `identifier` and `password` fields.
2. Retrieves the user by their identifier via the ORM repository.
3. Verifies the password with `PasswordManager::verify()`.
4. On success: regenerates the session, stores the ID and the timestamp.

**Session expiration:**

```php
// Inside check()
if ((time() - $lastActivity) > $this->timeout) {
    $this->logout(); // Removes the session keys
    return false;
}
// Automatic renewal of the last activity
$this->session->set('_auth_last_activity', time());
```

The default timeout is **1800 seconds** (30 minutes), configurable via `options.timeout`.

**Role verification:** `hasRole()` receives a `?RoleConfig` dependency and returns `false` directly if it is `null`.

---

## TokenGuard

**File:** `Guard/TokenGuard.php`

Authenticates requests via a **JWT token** sent in the `Authorization: Bearer <token>` header. Stateless — no data is stored server-side.

**Token extraction:**

```php
$header = $this->request->header('Authorization');
// Must start with 'Bearer '
$token = substr($header, 7);
```

**Generating a token:**

```php
// The payload only contains 'sub' => the user's id
$token = $auth->generateToken($userObject);
```

**Differences from `SessionGuard`:**

- `login()` is an empty method (stateless).
- `logout()` only clears the in-memory cached payload.
- No server-side storage.

**Retrieving the user:** decodes the token, extracts the `sub` claim, loads the entity from the database via the `EntityManager`.

**Role verification:** same logic as `SessionGuard`, based on the same `?RoleConfig`.

---

## JwtManager

**File:** `JwtManager.php`

Handles the generation, decoding, and validation of JWT tokens **with no external dependency** (native PHP HMAC-SHA256).

### Generation

```php
$jwt = new JwtManager(
    secret: 'my-very-long-secret-key',
    expiration: 3600,  // 1 hour
    algorithm: 'HS256'
);

$token = $jwt->generate(['sub' => 42, 'role' => 'admin']);
// → header.payload.signature (base64url)
```

The generated payload automatically contains the `iat` (issued at) and `exp` (expiration) claims.

### Decoding and Validation

```php
// Decodes and verifies the signature + expiration
$payload = $jwt->decode($token);
// ['sub' => 42, 'role' => 'admin', 'iat' => ..., 'exp' => ...]

// Silent verification (no exception)
$isValid = $jwt->isValid($token); // true | false
```

**Exceptions thrown by `decode()`:**

| Situation | Message |
|-----------|---------|
| Invalid format (not 3 parts) | `Invalid token format.` |
| Incorrect signature | `Invalid token signature.` |
| Undecodable payload | `Invalid token payload.` |
| Expired token | `The token has expired.` |

**Security:** signature comparison uses `hash_equals()` to prevent timing attacks.

---

## PasswordManager

**File:** `PasswordManager.php`

Encapsulates PHP's native password management functions. `PASSWORD_DEFAULT` algorithm (bcrypt), cost 12.

```php
$pm = $container->get(PasswordManager::class);

// Hashing (bcrypt, cost 12)
$hash = $pm->hash('my-password');

// Verification
$isValid = $pm->verify('my-password', $hash); // true

// Check whether the hash needs to be recomputed (after a parameter change)
if ($pm->needsRehash($hash)) {
    $user->setPassword($pm->hash($plainPassword));
}

// Generate a random password (hex, 12 bytes = 24 characters)
$generated = $pm->generate(12);

// Info about the algorithm used
$info = $pm->getInfo($hash);
// ['algo' => PASSWORD_BCRYPT, 'algoName' => 'bcrypt', 'options' => ['cost' => 12]]
```

---

## Controller Extension

**File:** `Extension/AuthControllerExtension.php`

Automatically injects two methods into every controller.

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
            return $this->jsonError('Invalid credentials', 401);
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

## Twig Extension

**File:** `Extension/AuthViewExtension.php`

Exposes three functions in every Twig template:

| Function | Description |
|----------|-------------|
| `auth_check()` | `true` if the user is logged in |
| `auth_user()` | Current user object (`null` if logged out) |
| `auth_has_role(role)` | `true` if the user has the given role |

```twig
{% if auth_check() %}
    <span>Hello, {{ auth_user().getName() }}</span>

    {% if auth_has_role('admin') %}
        <a href="/admin">Administration</a>
    {% endif %}

    <a href="/logout">Logout</a>
{% else %}
    <a href="/login">Login</a>
{% endif %}
```