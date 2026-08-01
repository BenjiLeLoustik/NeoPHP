# Controller and View Extensions

The `Extension` module provides an automatic discovery and application mechanism for extensions targeting two contexts: **controllers** (`AbstractController`) and **Twig views**. It relies on the PHP 8 `#[Extension]` attribute and a recursive scan of the source code.

---

## Module Files

| File | Role |
|---|---|
| `ExtensionManager.php` | Main manager: discovery and application of extensions |
| `Attribute/Extension.php` | PHP 8 attribute for marking a class as an extension |
| `Enum/ExtensionTypeEnum.php` | Enumeration of extension types (`CONTROLLER`, `VIEW`) |

---

## Extension Types

`ExtensionTypeEnum` is a string enum with two cases:

| Case | Value | Target |
|---|---|---|
| `CONTROLLER` | `'controller'` | `AbstractController` extensions |
| `VIEW` | `'twig'` | Twig extensions (functions, filters, globals) |

---

## The `#[Extension]` Attribute

The `#[Extension]` attribute must be placed on the extension class to be automatically discovered.

```php
use Neo\Core\Extension\Attribute\Extension;
use Neo\Core\Extension\Enum\ExtensionTypeEnum;

#[Extension(type: ExtensionTypeEnum::CONTROLLER)]
class MyControllerExtension implements ControllerExtensionInterface
{
    // ...
}
```

```php
#[Extension(type: ExtensionTypeEnum::VIEW)]
class MyTwigExtension implements TwigExtensionInterface
{
    // ...
}
```

---

## Creating a Controller Extension

A controller extension must implement `ControllerExtensionInterface` and define the `extend()` method. It is automatically called every time a controller is instantiated.

```php
namespace App\Extension;

use Neo\Core\Controller\AbstractController;
use Neo\Core\Controller\Interface\ControllerExtensionInterface;
use Neo\Core\DI\Container;
use Neo\Core\Extension\Attribute\Extension;
use Neo\Core\Extension\Enum\ExtensionTypeEnum;

#[Extension(type: ExtensionTypeEnum::CONTROLLER)]
class AuthExtension implements ControllerExtensionInterface
{
    public function extend(AbstractController $controller, Container $container): void
    {
        $auth = $container->get(AuthService::class);

        // Inject an object or data into the controller
        if (method_exists($controller, 'setAuth')) {
            $controller->setAuth($auth);
        }
    }
}
```

---

## Creating a Twig Extension

A Twig extension must implement `TwigExtensionInterface`. It is retrieved and passed to the Twig engine when it is initialized by the `ViewModule`.

```php
namespace App\Extension;

use Neo\Core\Extension\Attribute\Extension;
use Neo\Core\Extension\Enum\ExtensionTypeEnum;
use Neo\Core\View\Interface\TwigExtensionInterface;
use Twig\TwigFunction;

#[Extension(type: ExtensionTypeEnum::VIEW)]
class UrlExtension implements TwigExtensionInterface
{
    public function __construct(
        private readonly RouterService $router
    ) {}

    public function getFunctions(): array
    {
        return [
            new TwigFunction('url', fn(string $name, array $params = []) =>
                $this->router->generate($name, $params)
            ),
        ];
    }
}
```

---

## ExtensionManager

`ExtensionManager` is instantiated with the container and exposes three public methods:

### `getControllerExtensions(): array`

Returns the list of discovered `ControllerExtensionInterface` instances. The result is cached as a property (lazy load).

```php
$extensions = $extensionManager->getControllerExtensions();
```

### `getViewExtensions(): array`

Returns the list of discovered `TwigExtensionInterface` instances.

```php
$extensions = $extensionManager->getViewExtensions();
```

### `applyToController(AbstractController $controller): void`

Applies every controller extension to a given controller. Automatically called by the framework when each controller is instantiated.

```php
$extensionManager->applyToController($myController);
// Calls $extension->extend($controller, $container) for each extension
```

---

## Automatic Discovery

`ExtensionManager` recursively scans two directories at the project root:

```
/neo    ← framework extensions
/src    ← application extensions
```

For every PHP file whose name ends with `Extension.php`, it:

1. Extracts the FQCN (namespace + class) by analyzing the source
2. Checks that the class is neither abstract nor an interface
3. Looks for the `#[Extension]` attribute via `ScannerAttributeManager`
4. Filters based on the requested type (`CONTROLLER` or `VIEW`)
5. Resolves the instance through the container (including auto-wiring)

The scan is run **only once per type** thanks to lazy-loading (`??=`).

---

## Naming Conventions

To be detected, an extension class must:

- Have a filename ending with `Extension.php` (e.g. `AuthExtension.php`)
- Carry the `#[Extension(type: ExtensionTypeEnum::CONTROLLER)]` or `#[Extension(type: ExtensionTypeEnum::VIEW)]` attribute
- Not be abstract nor an interface
- Be located inside `/neo` or `/src` (recursively)

```
src/
  Extension/
    AuthExtension.php          ← discovered
    UrlExtension.php           ← discovered
    Abstract/
      BaseExtension.php        ← ignored (abstract if marked abstract)
```