# CSRF

The `Csrf` submodule protects forms and HTTP requests against Cross-Site Request Forgery attacks via session tokens.

---

## Summary

1. [Structure](#structure)
2. [CsrfManager](#csrfmanager)
3. [CsrfTokenManager](#csrftokenmanager)
4. [CsrfToken](#csrftoken)
5. [Twig Extension](#twig-extension)
6. [CsrfMiddleware](#csrfmiddleware)

---

## Structure

```
Csrf/
├── CsrfManager.php                     # Single token per session
├── CsrfTokenManager.php                # Named tokens with expiration
├── CsrfModule.php                      # DI registration
├── Exception/
│   └── CsrfException.php
├── Extension/
│   └── CsrfViewExtension.php           # csrf_token() Twig function
└── Token/
    └── CsrfToken.php                   # Token value object
```

---

## CsrfManager

**File:** `CsrfManager.php`

Manages a **single token per session**, stored under the `_csrf_token` key. This is the component used by `CsrfMiddleware`.

```php
$csrf = $container->get(CsrfManager::class);

// Generate or retrieve the current session's token
$token = $csrf->generate();

// Read the token without creating it
$token = $csrf->token();

// Validate the token sent in the request
$isValid = $csrf->validate();

// Force the token to regenerate
$csrf->refresh();
```

**Token sources in the request (in order):**

1. `body('_csrf_token')` — hidden field in an HTML form.
2. `header('X-CSRF-Token')` — HTTP header (for AJAX requests).

**Secure comparison:** `hash_equals()` is used to prevent timing attacks.

**Example in a controller:**

```php
#[Route('/profile/edit', 'POST')]
public function edit(): Response
{
    // CsrfMiddleware validates automatically if configured.
    // Otherwise, manual validation:
    if (!$this->csrfManager->validate()) {
        throw new \RuntimeException('Invalid CSRF token.');
    }
    // ...
}
```

---

## CsrfTokenManager

**File:** `CsrfTokenManager.php`

Advanced alternative to `CsrfManager`. Manages **named tokens with individual expiration**, one per form, in parallel.

```php
$manager = $container->get(CsrfTokenManager::class);

// Generate a token for a specific form (expires in 3600s)
$token = $manager->generateToken('contact_form', expiry: 3600);
$tokenValue = $token->getValue(); // 64-character hex string

// Retrieve an existing token
$token = $manager->getToken('contact_form'); // CsrfToken|null

// Validate and consume the token (invalidate: true = removed after validation)
$isValid = $manager->validateToken('contact_form', $submittedValue, invalidate: true);
```

**Storage:** `$_SESSION['_csrf_tokens']['<id>']`

**Expired token:** if expired at validation time, it is removed from the session and the method returns `false`.

**CLI behavior:** in a CLI context (`PHP_SAPI === 'cli'`), every operation is a silent no-op.

---

## CsrfToken

**File:** `Token/CsrfToken.php`

Value object representing a named token.

```php
$token->getId();       // 'contact_form'
$token->getValue();    // 64-character hexadecimal string (32 bytes)
$token->getExpiry();   // Unix expiration timestamp
$token->isExpired();   // true if time() > expiry
```

---

## Twig Extension

**File:** `Extension/CsrfViewExtension.php`

Exposes the `csrf_token()` function in every Twig template. If the token does not yet exist in the session, it is created automatically.

```twig
{# Default token (id 'default') #}
<form method="POST" action="{{ path('profile.edit') }}">
    <input type="hidden" name="_csrf_token" value="{{ csrf_token() }}">
    {# ... form fields ... #}
    <button type="submit">Save</button>
</form>

{# Named token for a specific form #}
<input type="hidden" name="_csrf_token" value="{{ csrf_token('contact_form') }}">
```

---

## CsrfMiddleware

**File:** `../Middleware/Default/CsrfMiddleware.php`

Automatically validates the CSRF token for every non-safe request. `GET`, `HEAD`, and `OPTIONS` methods are ignored.

```php
use Neo\Core\Security\Middleware\Attribute\Middleware;
use Neo\Core\Security\Middleware\Default\CsrfMiddleware;

// On an entire controller
#[Middleware(use: CsrfMiddleware::class, message: 'Missing or invalid CSRF token.')]
class MyController extends AbstractController { ... }

// On a specific method
#[Middleware(use: CsrfMiddleware::class)]
#[Route('/settings', 'POST')]
public function update(): Response { ... }
```

See [Middleware/README.md](../Middleware/README.md) for the full pipeline configuration.