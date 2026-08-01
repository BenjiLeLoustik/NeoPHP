# Flash Messages

`Neo\Core\Http\Client\Flash\Flash` handles ephemeral messages stored in the session and consumed on the next read (flash message pattern).

---

## Summary

1. [Structure](#structure)
2. [Configuration](#configuration)
3. [Adding a Message](#adding-a-message)
4. [Reading Messages](#reading-messages)
5. [HTML Rendering](#html-rendering)
6. [Controller Extension](#controller-extension)
7. [Twig Function](#twig-function)

---

## Structure

```
Client/Flash/
├── Flash.php                           # Flash messages
└── Extension/
    ├── FlashControllerExtension.php    # Injects getFlash() into controllers
    └── FlashViewExtension.php          # Exposes flashes() in Twig
```

---

## Configuration

Configured from `session.config.php`, under the `flash` key:

```php
return [
    'flash' => [
        'session_key' => '_flash',
        'auto_expire' => true,       // Clears messages after reading
        'types'       => ['success', 'error', 'warning', 'info'],
    ],
];
```

---

## Adding a Message

```php
$flash = $container->get(Flash::class);

$flash->add('success', 'Your profile has been updated.');
$flash->add('error', 'An error occurred.');
$flash->add('warning', 'Your session is about to expire.');
$flash->add('info', 'An update is available.');
```

The type must be declared in the configuration (`types`). Otherwise, a `FrameworkException` is thrown.

---

## Reading Messages

```php
// Retrieves every message as an array
// If auto_expire = true, messages are cleared after this read
$messages = $flash->getAll();
// [
//   ['type' => 'success', 'message' => 'Your profile has been updated.'],
//   ['type' => 'error',   'message' => 'An error occurred.'],
// ]

// Check whether any messages are pending
if ($flash->has()) {
    // ...
}
```

---

## HTML Rendering

```php
echo $flash->render();
// <span class='flash-message success'>Your profile has been updated.</span>
// <span class='flash-message error'>An error occurred.</span>
```

Values are passed through `htmlspecialchars()` to prevent XSS.

---

## Controller Extension

**File:** `Extension/FlashControllerExtension.php`

Automatically injects `getFlash()` into every controller.

```php
class UserController extends AbstractController
{
    #[Route('/profile', 'POST')]
    public function update(): Response
    {
        // ... processing

        $this->getFlash()->add('success', 'Profile updated.');
        return $this->redirect('/profile');
    }
}
```

---

## Twig Function

**File:** `Extension/FlashViewExtension.php`

Exposes the `flashes()` function in every Twig template. The result is marked `is_safe: html`.

```twig
{# In a layout or a partial #}
{{ flashes() }}
```

Generates the HTML rendering of every pending flash message (equivalent to `Flash::render()`).