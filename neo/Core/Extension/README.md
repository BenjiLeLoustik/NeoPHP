# Module Extension — Extensions de Contrôleurs et de Vues

Le module `Extension` fournit un mécanisme de découverte automatique et d'application d'extensions pour deux cibles : les **contrôleurs** (`AbstractController`) et les **vues Twig**. Il repose sur l'attribut PHP 8 `#[Extension]` et un scan récursif du code source.

---

## Fichiers du module

| Fichier | Rôle |
|---|---|
| `ExtensionManager.php` | Gestionnaire principal : découverte et application des extensions |
| `Attribute/Extension.php` | Attribut PHP 8 pour marquer une classe comme extension |
| `Enum/ExtensionTypeEnum.php` | Enumération des types d'extension (`CONTROLLER`, `VIEW`) |

---

## Types d'extension

`ExtensionTypeEnum` est une enum string avec deux cas :

| Cas | Valeur | Cible |
|---|---|---|
| `CONTROLLER` | `'controller'` | Extensions de `AbstractController` |
| `VIEW` | `'twig'` | Extensions Twig (fonctions, filtres, globals) |

---

## L'attribut `#[Extension]`

L'attribut `#[Extension]` doit être placé sur la classe extension pour être découvert automatiquement.

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

## Créer une extension de contrôleur

Une extension de contrôleur doit implémenter `ControllerExtensionInterface` et définir la méthode `extend()`. Elle est appelée automatiquement à chaque fois qu'un contrôleur est instancié.

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

        // Injecter un objet ou des données dans le contrôleur
        if (method_exists($controller, 'setAuth')) {
            $controller->setAuth($auth);
        }
    }
}
```

---

## Créer une extension Twig

Une extension Twig doit implémenter `TwigExtensionInterface`. Elle est récupérée et passée au moteur Twig lors de son initialisation par le `ViewModule`.

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

`ExtensionManager` est instancié avec le conteneur et expose trois méthodes publiques :

### `getControllerExtensions(): array`

Retourne la liste des instances de `ControllerExtensionInterface` découvertes. Le résultat est mis en cache en propriété (lazy load).

```php
$extensions = $extensionManager->getControllerExtensions();
```

### `getViewExtensions(): array`

Retourne la liste des instances de `TwigExtensionInterface` découvertes.

```php
$extensions = $extensionManager->getViewExtensions();
```

### `applyToController(AbstractController $controller): void`

Applique toutes les extensions de contrôleur à un contrôleur donné. Appelé automatiquement par le framework à l'instanciation de chaque contrôleur.

```php
$extensionManager->applyToController($myController);
// Appelle $extension->extend($controller, $container) pour chaque extension
```

---

## Découverte automatique

`ExtensionManager` scanne récursivement deux répertoires à la racine du projet :

```
/neo    ← extensions du framework
/src    ← extensions applicatives
```

Pour chaque fichier PHP dont le nom se termine par `Extension.php`, il :

1. Extrait le FQCN (namespace + classe) par analyse du source
2. Vérifie que la classe n'est ni abstraite ni une interface
3. Cherche l'attribut `#[Extension]` via `ScannerAttributeManager`
4. Filtre selon le type demandé (`CONTROLLER` ou `VIEW`)
5. Résout l'instance via le conteneur (auto-wiring inclus)

Le scan est exécuté **une seule fois par type** grâce au lazy-loading (`??=`).

---

## Conventions de nommage

Pour être détectée, une classe extension doit :

- Avoir un nom de fichier se terminant par `Extension.php` (ex. : `AuthExtension.php`)
- Porter l'attribut `#[Extension(type: ExtensionTypeEnum::CONTROLLER)]` ou `#[Extension(type: ExtensionTypeEnum::VIEW)]`
- Ne pas être abstraite ni une interface
- Se trouver dans `/neo` ou `/src` (récursivement)

```
src/
  Extension/
    AuthExtension.php          ← découvert
    UrlExtension.php           ← découvert
    Abstract/
      BaseExtension.php        ← ignoré (abstraite si marquée abstract)
```
