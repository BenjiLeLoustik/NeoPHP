# Cookie

`Neo\Core\Http\Client\Cookie\Cookie` encapsulates PHP cookie management with automatic prefixing and centralized configuration.

---

## Summary

1. [Structure](#structure)
2. [Configuration](#configuration)
3. [Usage](#usage)
4. [Controller Extension](#controller-extension)

---

## Structure

```
Client/Cookie/
├── Cookie.php                          # Cookie management
└── Extension/
    └── CookieControllerExtension.php   # Injects getCookie() into controllers
```

---

## Configuration

Configured from `session.config.php`, under the `cookie` key:

```php
return [
    'cookie' => [
        'prefix'    => 'neo_',
        'lifetime'  => 2592000,  // 30 days (in seconds)
        'path'      => '/',
        'domain'    => '',
        'secure'    => true,     // HTTPS-only cookie
        'http_only' => true,     // Cookie inaccessible from JavaScript
        'same_site' => 'Lax',
    ],
];
```

Every cookie name is automatically prefixed (e.g. `user_theme` → `neo_user_theme`). The `get`, `has`, and `remove` methods transparently apply the same prefix.

---

## Usage

```php
$cookie = $container->get(Cookie::class);

// Write a cookie (default config values)
$cookie->set('user_theme', 'dark');

// With custom parameters
$cookie->set(
    name: 'remember_token',
    value: $token,
    expire: time() + 86400,   // expires in 1 day
    path: '/',
    domain: 'example.com',
    secure: true,
    httpOnly: true
);

// Read
$theme = $cookie->get('user_theme', 'light'); // value or default

// Check existence
$cookie->has('user_theme'); // true/false

// Remove (expires in the past)
$cookie->remove('user_theme');

// All raw cookies (full $_COOKIE, unfiltered by prefix)
$cookie->all();
```

---

## Controller Extension

**File:** `Extension/CookieControllerExtension.php`

Automatically injects `getCookie()` into every controller.

```php
class PreferenceController extends AbstractController
{
    #[Route('/theme', 'POST')]
    public function setTheme(): Response
    {
        $theme = $this->getRequest()->body('theme', 'light');
        $this->getCookie()->set('user_theme', $theme);

        return $this->redirect('/');
    }

    #[Route('/theme', 'GET')]
    public function getTheme(): JsonResponse
    {
        $theme = $this->getCookie()->get('user_theme', 'light');
        return $this->jsonSuccess(['theme' => $theme]);
    }
}
```