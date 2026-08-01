# Error and Exception Handling

The `Error` module centralizes the capture, logging, dispatching, and rendering of every error and exception occurring in NeoPHP. It distinguishes between `dev` environments (full trace) and `prod` environments (generic, safe message), and integrates with the Logger, Event, and View modules.

---

## Module Files

| File | Role |
|---|---|
| `ErrorManager.php` | Main error and exception manager |
| `ErrorModule.php` | Initialization module (registration in the container) |
| `Exception/FrameworkException.php` | Enriched base exception of the framework |

---

## FrameworkException

`Neo\Core\Error\Exception\FrameworkException` extends `\Exception` with two additional fields: a human-readable **title** and a structured **context**.

### Constructor

```php
new FrameworkException(
    title: 'Access Denied',
    message: 'You do not have permission to access this resource.',
    code: 403,
    context: ['user_id' => 42, 'route' => '/admin'],
    previous: $previousException // optional
);
```

### Methods

```php
$e->getTitle();   // string: short title (e.g. "Access Denied")
$e->getContext(); // array<string, mixed>: context data
$e->getMessage(); // string: detailed message (inherited from \Exception)
$e->getCode();    // int: HTTP status code or error code
```

### Converting from any `Throwable`

```php
$frameworkException = FrameworkException::fromThrowable($e, 'Custom error');
```

The context automatically includes `file`, `line`, `trace`, and `previous` from the original exception.

---

## ErrorManager

`Neo\Core\Error\ErrorManager` is the central manager. It registers itself as a native PHP handler and orchestrates: logging, event dispatching, and response rendering.

### Bootstrap initialization (before the container)

For very early errors (before the container is available), a static fallback handler can be registered:

```php
ErrorManager::registerBootstrap();
```

This handler automatically detects the environment (`dev` if `localhost` or `127.0.0.1`, `prod` otherwise) and renders a fallback HTML page.

### Full initialization (via the module)

```php
$errorManager = new ErrorManager($container);
$errorManager->setEnv('dev'); // or 'prod'
$errorManager->register();    // installs set_exception_handler + set_error_handler
```

---

### `handleException(Throwable $e)` behavior

Processing follows four steps, in order:

**1. Logging**

Attempts to write via the container's `LoggerModule` to the `framework` channel. On failure, writes directly to `storage/logs/framework.log`.

```
[2026-07-28 14:30:00] Neo\Core\Error\Exception\FrameworkException: Message here
  in /app/src/Service/MyService.php:42
```

**2. Event dispatching**

An `ExceptionEvent` is dispatched via the `EventModule`, allowing any listener to intercept the error.

**3. Error view rendering**

If a `views/errors/{code}.html.twig` file exists and the `ViewModule` is available, the Twig view is rendered with the following variables:

```twig
{# views/errors/404.html.twig #}
<h1>{{ title }}</h1>
<p>{{ message }}</p>
{% if context is not empty %}
    <pre>{{ context | json_encode(constant('JSON_PRETTY_PRINT')) }}</pre>
{% endif %}
```

In `prod`, `message` is replaced with a generic message based on the HTTP code, and `context` is cleared.

**4. HTML fallback rendering**

If no Twig view is available, inline HTML is generated.

---

### Inline HTML fallback rendering

The static method `renderFallbackHtml()` produces a complete, self-contained HTML page with no external dependencies.

**In `dev` mode**: displays the exception's class name, file, line, stack trace (max 50 frames), and context.

**In `prod` mode**: generic message based on the HTTP code:

| Code | Prod message |
|---|---|
| 404 | The page you are looking for could not be found. |
| 403 | You do not have permission to access this resource. |
| 401 | You must be authenticated to access this resource. |
| 405 | The HTTP method used is not allowed for this route. |
| 419 | Your session has expired, please refresh the page. |
| 422 | The submitted data is invalid. |
| 429 | Too many requests, please try again in a few moments. |
| 5xx | An internal error has occurred, please try again later. |

The interface color adapts to the code:

| Range | Color |
|---|---|
| 5xx | Orange (`#c2410c`) |
| 404 | Blue (`#1d4ed8`) |
| 403 / 401 | Purple (`#7e22ce`) |
| Others | Red (`#b91c1c`) |

---

### Handling native PHP errors

`handleError()` converts any PHP error (`E_WARNING`, `E_NOTICE`, etc.) into an `ErrorException`, then delegates to `handleException()`. Errors suppressed with `@` are ignored.

```php
// Any native PHP error will be treated as an exception
trigger_error('My message', E_USER_WARNING);
```

---

## ErrorModule

`ErrorModule` implements `ModuleInterface` and declares the module's dependencies:

```php
// Declared dependencies
ConfigModule::class
EventModule::class
LoggerModule::class
ViewModule::class
```

It registers `ErrorManager` in the container and initializes it:

```php
// Registration
$container->set(ErrorManager::class, fn(Container $c) => new ErrorManager($c));

// Initialization: register() is called except in a test context (_NEO_TEST_PROJECT)
$errorHandler->register();
$errorHandler->setEnv($env); // read from the app.environment config
```

---

## Creating your own exceptions

Every framework exception extends `FrameworkException`:

```php
namespace App\Exception;

use Neo\Core\Error\Exception\FrameworkException;

class ValidationException extends FrameworkException
{
    public function __construct(array $errors)
    {
        parent::__construct(
            title: 'Invalid Data',
            message: 'Validation failed.',
            code: 422,
            context: ['errors' => $errors]
        );
    }
}
```

```php
throw new ValidationException([
    'email' => 'Invalid email address.',
    'name'  => 'Name is required.',
]);
```

`ErrorManager` will catch this exception, log it, and render the `views/errors/422.html.twig` page if it exists.

---

## Integration with the Profiler

If the `NEO_PROFILER_ENABLED` constant is defined and true, the Profiler toolbar is automatically injected into the error page's HTML, just before `</body>`.