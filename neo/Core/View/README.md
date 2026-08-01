# View

The View module integrates the **Twig** template engine into NeoPHP. It exposes the `ViewManager` for rendering templates, an extension system allowing Twig functions and filters to be added from any module, and a controller extension that injects the `render()` and `template()` methods directly into controllers.

---

## Table of Contents

1. [Module Structure](#module-structure)
2. [Twig Configuration](#twig-configuration)
3. [ViewManager](#viewmanager)
4. [Controller Extension: render() and template()](#controller-extension-render-and-template)
5. [TwigExtensionInterface](#twigextensioninterface)
6. [Creating a Twig Extension](#creating-a-twig-extension)
7. [Twig Globals](#twig-globals)
8. [Error Handling](#error-handling)
9. [ViewModule](#viewmodule)

---

## Module Structure

```
View/
├── ViewManager.php                     # Main Twig manager
├── ViewModule.php                      # Registration in the DI container
├── Interface/
│   └── TwigExtensionInterface.php      # Contract for Twig extensions
├── Extension/
│   └── ViewControllerExtension.php     # Injects render() and template() into controllers
└── Exception/
    └── ViewException.php               # Exception specific to rendering errors
```

---

## Twig Configuration

Configuration is read from two files: `twig.config.php` and `app.config.php`.

**`src/MyProject/Config/twig.config.php`:**

```php
return [
    'cache'            => false,        // true in production
    'debug'            => true,         // Adds the DebugExtension and {{ dump() }}
    'auto_reload'      => true,         // Recompiles modified templates
    'auto_escape'      => 'html',       // Automatic HTML escaping
    'charset'          => 'UTF-8',
    'strict_variables' => false,        // true = exception if variable unknown
    'options'          => [],           // Additional options passed to Twig\Environment
];
```

**`src/MyProject/Config/app.config.php`:**

```php
return [
    'date' => [
        'timezone' => 'Europe/Paris',   // Twig and PHP timezone
    ],
    'general' => [
        'name'    => 'My Application',
        'version' => '1.0.0',
    ],
];
```

The timezone is applied both to PHP (`date_default_timezone_set`) and to Twig (`CoreExtension::setTimezone`).

When `cache` is `true`, compiled templates are stored in `Storage/var/cache/Twig/`.

---

## ViewManager

`Neo\Core\View\ViewManager` wraps the `Twig\Environment` instance and provides the rendering methods.

### Rendering Methods

```php
use Neo\Core\View\ViewManager;

$view = $container->get(ViewManager::class);

// Render with an exception if the template does not exist
$html = $view->render('articles/list.twig', [
    'articles' => $articles,
    'title'    => 'All articles',
]);

// Silent render: returns null if the template cannot be found
$html = $view->renderIfExists('partials/sidebar.twig', ['user' => $user]);
if ($html !== null) {
    // display the sidebar
}

// Direct access to the Twig\Environment instance
$twig = $view->getTwig();
```

### Adding a Twig Extension

```php
$view->addExtension(new MyExtension());
```

`addExtension()` iterates over the functions and filters returned by the extension and registers them in Twig via `TwigFunction` and `TwigFilter`. Each entry can be either a direct callable, or an array `['callable' => ..., 'options' => [...]]`.

---

## Controller Extension: render() and template()

`Neo\Core\View\Extension\ViewControllerExtension` is a controller extension (annotated `#[Extension(type: ExtensionTypeEnum::CONTROLLER)]`) that automatically injects two methods into all controllers.

### render()

Renders a Twig template and directly returns a `Response` object with the `Content-Type: text/html; charset=UTF-8` header.

```php
use Neo\Core\Controller\AbstractController;

class ArticleController extends AbstractController
{
    public function list(): Response
    {
        $articles = $this->get(ArticleRepository::class)->findAll();

        return $this->render('articles/list.twig', [
            'articles' => $articles,
        ]);
    }

    public function detail(int $id): Response
    {
        $article = $this->get(ArticleRepository::class)->findById($id);

        return $this->render('articles/detail.twig', [
            'article' => $article,
            'title'   => $article->title,
        ]);
    }
}
```

### template()

Renders a Twig template and returns the **HTML content as a string** (without creating a `Response`). Useful for including a fragment in a more complex response or for rendering partial components.

```php
class EmailController extends AbstractController
{
    public function send(): Response
    {
        $emailContent = $this->template('emails/welcome.twig', [
            'name' => 'Alice',
        ]);

        $this->get(Mailer::class)->send(
            to: 'alice@example.com',
            subject: 'Welcome',
            body: $emailContent
        );

        return $this->json(['message' => 'Email sent.']);
    }
}
```

### The `app` Global Variable in Templates

`ViewControllerExtension` enriches the `app` global variable available in all templates by automatically adding the current `Session` and `Cookie`:

```twig
{# Access session data in Twig #}
{% if app.session.get('user_id') %}
    Logged in as: {{ app.session.get('user_name') }}
{% endif %}

{# Access the application name (from general config) #}
<title>{{ app.name }}</title>

{# Access cookies #}
{% if app.cookie.has('lang') %}
    Language: {{ app.cookie.get('lang') }}
{% endif %}
```

---

## TwigExtensionInterface

`Neo\Core\View\Interface\TwigExtensionInterface` is the contract that every Twig extension must implement.

```php
interface TwigExtensionInterface
{
    /**
     * @return array<string, mixed>
     */
    public function getFunctions(): array;

    /**
     * @return array<string, mixed>
     */
    public function getFilters(): array;
}
```

Each method returns an array indexed by the **function/filter name** in Twig. The value can be:

**Simple format (direct callable):**

```php
'myFunction' => fn(string $param): string => strtoupper($param),
```

**Extended format (with Twig options):**

```php
'myFunction' => [
    'callable' => fn(string $param): string => strtoupper($param),
    'options'  => ['is_safe' => ['html']],  // TwigFunction/TwigFilter options
],
```

---

## Creating a Twig Extension

To expose custom functions or filters in templates, simply create a class implementing `TwigExtensionInterface` and annotate it with `#[Extension(type: ExtensionTypeEnum::VIEW)]` so it is automatically detected and registered.

```php
<?php
declare(strict_types=1);

namespace Neo\Src\MyProject\Extension;

use Neo\Core\Extension\Attribute\Extension;
use Neo\Core\Extension\Enum\ExtensionTypeEnum;
use Neo\Core\View\Interface\TwigExtensionInterface;

#[Extension(type: ExtensionTypeEnum::VIEW)]
class MyTwigExtension implements TwigExtensionInterface
{
    public function getFunctions(): array
    {
        return [
            // Simple function
            'format_price' => fn(float $amount, string $currency = 'EUR'): string
                => number_format($amount, 2, ',', ' ') . ' ' . $currency,

            // Function with Twig options (is_safe: html to avoid re-escaping)
            'badge' => [
                'callable' => fn(string $label, string $color = 'blue'): string
                    => "<span class=\"badge badge-{$color}\">{$label}</span>",
                'options' => ['is_safe' => ['html']],
            ],

            // Access to an injected service
            'article_count' => fn(): int => $this->articleRepository->count(),
        ];
    }

    public function getFilters(): array
    {
        return [
            'initials' => fn(string $name): string => implode('', array_map(
                fn(string $word): string => strtoupper($word[0]),
                explode(' ', $name)
            )),

            'truncate' => [
                'callable' => fn(string $text, int $length = 100): string
                    => mb_strlen($text) > $length
                        ? mb_substr($text, 0, $length) . '...'
                        : $text,
                'options' => [],
            ],
        ];
    }
}
```

**Usage in Templates:**

```twig
{# Functions #}
{{ format_price(article.price) }}
{{ format_price(article.price, 'USD') }}
{{ badge('New', 'green') }}
{{ article_count() }} articles available

{# Filters #}
{{ user.name|initials }}
{{ article.description|truncate(150) }}
```

---

## Twig Globals

The `app` variable is available globally in all templates. It is built from the `general` section of `app.config.php`.

```php
// app.config.php
return [
    'general' => [
        'name'        => 'MySite',
        'version'     => '2.1.0',
        'maintenance' => false,
        'support'     => 'support@mysite.com',
    ],
];
```

```twig
{# In any template #}
<title>{{ app.name }}</title>
<meta name="version" content="{{ app.version }}">

{% if app.maintenance %}
    <div class="alert">Site under maintenance.</div>
{% endif %}

{# Enriched by ViewControllerExtension during a controller's render() #}
Hello {{ app.session.get('user_name') ?? 'visitor' }}
```

---

## Error Handling

`ViewManager` converts Twig exceptions into `ViewException` with appropriate HTTP codes:

| Twig Exception | Code | Title |
|---|---|---|
| `Twig\Error\LoaderError` | 404 | Template Not Found |
| `Twig\Error\SyntaxError` | 500 | Template Syntax Error |
| `Twig\Error\RuntimeError` | 500 | Template Runtime Error |

`renderIfExists()` silently intercepts `LoaderError` and returns `null`, without throwing an exception.

```php
// In a service, handle template errors
try {
    $html = $view->render('my-template.twig', $data);
} catch (ViewException $e) {
    // $e->getCode() returns 404 if the template cannot be found
    // $e->getMessage() contains the detail of the Twig error
    logger()->error($e->getMessage());
    $html = '<p>Rendering error.</p>';
}
```

---

## ViewModule

`Neo\Core\View\ViewModule` registers `ViewManager` in the DI container with `ConfigModule` as a dependency.

```php
// Automatic registration by the framework
// The module declares its dependency on ConfigModule
class ViewModule implements ModuleInterface
{
    public function dependencies(): array
    {
        return [ConfigModule::class];
    }

    public function register(Container $container): void
    {
        $container->set(ViewManager::class, fn(Container $c) => new ViewManager($c));
    }

    public function init(Container $container): object
    {
        return $container->get(ViewManager::class);
    }
}
```

`ViewManager` is instantiated only once (singleton in the container) and shared across all controllers and services that need it.