# Session

`Neo\Core\Http\Client\Session\Session` encapsulates the native PHP session with centralized configuration and a silent no-op behavior in CLI.

---

## Summary

1. [Structure](#structure)
2. [Configuration](#configuration)
3. [Usage](#usage)
4. [Controller Extension](#controller-extension)
5. [CLI Behavior](#cli-behavior)

---

## Structure

```
Client/Session/
├── Session.php                          # PHP session management
└── Extension/
    └── SessionControllerExtension.php   # Injects getSession() into controllers
```

---

## Configuration

The session is configured from `session.config.php`, under the `session` key:

```php
return [
    'session' => [
        'enabled'   => true,
        'name'      => 'neo_session',
        'lifetime'  => 7200,       // Duration in seconds
        'secure'    => true,       // HTTPS-only cookie
        'http_only' => true,       // Cookie inaccessible from JavaScript
        'same_site' => 'Lax',     // SameSite policy
        'storage'   => [
            'enabled' => true,
            'handler' => 'files',  // File-based storage
        ],
    ],
];
```

Session files are stored in `src/<Project>/Storage/var/cache/session/`.

---

## Usage

```php
$session = $container->get(Session::class);

// Write
$session->set('user_id', 42);

// Read
$session->get('user_id');           // 42
$session->get('missing', 'default'); // 'default'

// Check existence
$session->has('user_id');           // true

// Remove a key
$session->remove('user_id');

// Access the whole session
$session->all();   // full $_SESSION array

// Clear the session
$session->clear(); // clears $_SESSION without destroying it

// Regenerate the ID (call after a login)
$session->regenerate();

// Destroy the session
$session->destroy();
```

---

## Controller Extension

**File:** `Extension/SessionControllerExtension.php`

Automatically injects `getSession()` into every controller.

```php
class AuthController extends AbstractController
{
    #[Route('/login', 'POST')]
    public function login(): Response
    {
        // ... credentials verification

        $this->getSession()->set('user_id', $user->getId());
        $this->getSession()->regenerate();

        return $this->redirect('/dashboard');
    }

    #[Route('/logout', 'POST')]
    public function logout(): Response
    {
        $this->getSession()->destroy();
        return $this->redirect('/login');
    }
}
```

---

## CLI Behavior

In a CLI context (`PHP_SAPI === 'cli'`), the constructor detects the environment and every method (`set`, `get`, `has`, `remove`, `all`, `clear`, `regenerate`, `destroy`) becomes a silent no-op. No PHP session is started.