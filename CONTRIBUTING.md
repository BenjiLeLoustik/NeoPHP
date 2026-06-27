# CONTRIBUTING — Créer un module dans NeoPHP

Ce guide explique comment ajouter un nouveau sous-système au cœur du framework (`neo/Core/`).
Il couvre le cycle de vie d'un module, l'extension du contrôleur de base, l'ajout de fonctions Twig, la rédaction des tests et la validation avant ouverture d'une PR.

## Sommaire

- [Prérequis](#prérequis)
- [Le système de modules](#le-système-de-modules)
- [Créer un module](#créer-un-module)
- [Ajouter des méthodes à AbstractController](#ajouter-des-méthodes-à-abstractcontroller)
- [Ajouter des fonctions et filtres Twig](#ajouter-des-fonctions-et-filtres-twig)
- [Écrire les tests](#écrire-les-tests)
- [Valider avant PR](#valider-avant-pr)

---

## Prérequis

- PHP >= 8.5
- Composer installé
- Dépendances à jour : `composer install`

---

## Le système de modules

### Comment fonctionne la découverte

Au démarrage, `Neo\App` crée un `ModuleManager` et appelle `discover(__DIR__ . '/Core')`.

Le `ModuleManager` parcourt récursivement `neo/Core/` et charge tout fichier dont le nom se termine par `Module.php`. Il retient les classes qui :

- implémentent `ModuleInterface`
- ne sont pas abstraites
- n'appartiennent pas à un namespace `Tests` ou `Fixture`

Il résout ensuite l'ordre de démarrage en fonction des dépendances déclarées par chaque module, puis appelle dans l'ordre :

1. `register(Container $container)` sur **tous** les modules
2. `boot(Container $container)` sur **tous** les modules (dans l'ordre des dépendances)

### Contrat de `ModuleInterface`

```php
// neo/Core/Module/Interface/ModuleInterface.php

interface ModuleInterface
{
    /** @return array<class-string> */
    public function dependencies(): array;

    public function register(Container $container): void;

    public function boot(Container $container): void;
}
```

| Méthode | Moment d'appel | Rôle |
|---------|---------------|------|
| `dependencies()` | Avant tout boot | Déclarer les modules dont ce module dépend |
| `register()` | Phase 1 | Enregistrer les services dans le conteneur DI |
| `boot()` | Phase 2 | Initialiser les services, brancher les extensions |

### Classe de base `AbstractModule`

Étendre `AbstractModule` plutôt qu'implémenter `ModuleInterface` directement :

- stocke le conteneur dans `$this->container`
- expose `$this->get(string $class)` comme raccourci vers `$container->get()`
- fournit un hook `resolveDependencies()` appelé au moment du `boot()`
- implémente par défaut `dependencies()` → `[]`

```php
// neo/Core/Module/Abstract/AbstractModule.php (extrait)

class AbstractModule implements ModuleInterface
{
    protected Container $container;

    public function dependencies(): array
    {
        return [];
    }

    public function register(Container $container): void {}

    public function boot(Container $container): void
    {
        $this->container = $container;
        $this->resolveDependencies();
    }

    protected function get(string $abstract): mixed
    {
        return $this->container->get($abstract);
    }

    protected function resolveDependencies(): void {}
}
```

---

## Créer un module

### 1. Choisir l'emplacement

Chaque sous-système a son propre dossier sous `neo/Core/`. Créer :

```
neo/Core/
└── MonSousSystème/
    ├── MonSousSystèmeModule.php   ← fichier obligatoire, détecté automatiquement
    ├── MonService.php
    └── Exception/
        └── MonSousSystèmeException.php
```

### 2. Nommer le fichier module

Le nom du fichier **doit** se terminer par `Module.php`. C'est le seul critère de découverte automatique.

### 3. Écrire le module

```php
<?php
declare(strict_types=1);

namespace Neo\Core\MonSousSystème;

use Neo\Core\DI\Container;
use Neo\Core\Module\Abstract\AbstractModule;
use Neo\Core\Utils\Config\ConfigModule; // exemple de dépendance

final class MonSousSystèmeModule extends AbstractModule
{
    /**
     * Déclarer les modules dont ce module a besoin.
     * Le ModuleManager garantit qu'ils sont bootés avant celui-ci.
     *
     * @return array<class-string>
     */
    public function dependencies(): array
    {
        return [
            ConfigModule::class,
            // ViewModule::class, si on ajoute une extension Twig
        ];
    }

    /**
     * Enregistrer les services dans le conteneur.
     * Toujours utiliser des factory closures (lazy initialization).
     */
    public function register(Container $container): void
    {
        $container->set(MonService::class, fn(Container $c) => new MonService($c));

        // Si ce module expose une extension Twig :
        // $container->set(MonViewExtension::class, fn(Container $c) => new MonViewExtension(
        //     $c->get(MonService::class)
        // ));
        // $container->tag(MonViewExtension::class, 'twig.extension');
    }

    /**
     * Initialiser les services critiques après l'enregistrement de tous les modules.
     * Appel explicite uniquement si une initialisation eager est nécessaire.
     */
    protected function resolveDependencies(): void
    {
        $this->get(MonService::class);
    }
}
```

### 4. Écrire le service

```php
<?php
declare(strict_types=1);

namespace Neo\Core\MonSousSystème;

use Neo\Core\DI\Container;

final class MonService
{
    public function __construct(private readonly Container $container)
    {
    }

    public function doSomething(): string
    {
        return 'résultat';
    }
}
```

### Règles importantes

- Toujours `declare(strict_types=1)` en tête de fichier.
- Les services enregistrés dans `register()` doivent être des **factory closures** : `fn(Container $c) => new MonService(...)`.
- Ne jamais résoudre un service dans `register()` — uniquement dans `boot()` / `resolveDependencies()`.
- Si votre module dépend d'un autre, le déclarer dans `dependencies()` et ne jamais supposer l'ordre de chargement.

---

## Ajouter des méthodes à AbstractController

Quand un module doit exposer des raccourcis dans les contrôleurs applicatifs (ex : `$this->monService()`), créer un fichier `*ControllerExtension.php`.

### Comment fonctionne la découverte

`AbstractController` scanne récursivement `neo/Core/` au moment de son instanciation et charge automatiquement tout fichier se terminant par `ControllerExtension.php` qui implémente `ControllerExtensionInterface`.

### Contrat

```php
// neo/Core/Controller/Interface/ControllerExtensionInterface.php

interface ControllerExtensionInterface
{
    public function extend(AbstractController $controller, Container $container): void;
}
```

### Créer une extension de contrôleur

```php
<?php
declare(strict_types=1);

namespace Neo\Core\MonSousSystème;

use Neo\Core\Controller\AbstractController;
use Neo\Core\Controller\Interface\ControllerExtensionInterface;
use Neo\Core\DI\Container;

final class MonSousSystèmeControllerExtension implements ControllerExtensionInterface
{
    public function extend(AbstractController $controller, Container $container): void
    {
        // Enregistrer une méthode appelable depuis le contrôleur
        $controller->registerMethod(
            'getMonService',
            fn(): MonService => $container->get(MonService::class)
        );

        // Enregistrer une propriété (lazy, mise en cache après le premier accès)
        $controller->registerProperty(
            'monService',
            fn(): MonService => $container->get(MonService::class)
        );
    }
}
```

### Utilisation dans un contrôleur applicatif

```php
final class PostController extends AbstractController
{
    #[Route(path: '/', name: 'index', methods: ['GET'])]
    public function index(): Response
    {
        // Via méthode enregistrée
        $service = $this->getMonService();

        // Via propriété enregistrée (équivalent, avec cache)
        $result = $this->monService->doSomething();

        return $this->render('pages/index.html.twig', ['result' => $result]);
    }
}
```

### API disponible dans `extend()`

| Appel | Comportement |
|-------|-------------|
| `$controller->registerMethod('nom', fn() => ...)` | Ajoute une méthode appelable via `$this->nom()` |
| `$controller->registerProperty('nom', fn() => ...)` | Ajoute une propriété lazy via `$this->nom` (mise en cache) |

---

## Ajouter des fonctions et filtres Twig

Quand un module doit exposer des helpers dans les templates Twig, créer un fichier `*ViewExtension.php`.

### Comment fonctionne l'enregistrement

`ViewModule` récupère tous les services tagués `'twig.extension'` dans le conteneur et les passe à `ViewManager::addExtension()` au moment du boot. Il suffit donc de :

1. Implémenter `TwigExtensionInterface`
2. Enregistrer le service dans le module avec `$container->tag($class, 'twig.extension')`

### Contrat

```php
// neo/Core/View/Interface/TwigExtensionInterface.php

interface TwigExtensionInterface
{
    /** @return array<string, mixed> */
    public function getFunctions(): array;

    /** @return array<string, mixed> */
    public function getFilters(): array;
}
```

### Créer une extension Twig

```php
<?php
declare(strict_types=1);

namespace Neo\Core\MonSousSystème;

use Neo\Core\View\Interface\TwigExtensionInterface;

final class MonSousSystèmeViewExtension implements TwigExtensionInterface
{
    public function __construct(private readonly MonService $service)
    {
    }

    /**
     * @return array<string, array{callable: \Closure, options: array<string, mixed>}>
     */
    public function getFunctions(): array
    {
        return [
            // Nom Twig => ['callable' => closure, 'options' => []]
            'mon_helper' => [
                'callable' => fn(string $valeur) => $this->service->doSomething(),
                'options'  => [],
            ],
            'mon_autre_helper' => [
                'callable' => fn(int $n) => $n * 2,
                'options'  => [],
            ],
        ];
    }

    /**
     * @return array<string, array{callable: \Closure, options: array<string, mixed>}>
     */
    public function getFilters(): array
    {
        return [
            // Utilisable comme filtre : {{ valeur|mon_filtre }}
            'mon_filtre' => [
                'callable' => fn(string $v) => strtoupper($v),
                'options'  => [],
            ],
        ];
    }
}
```

### Brancher l'extension dans le module

Ajouter dans `MonSousSystèmeModule` :

```php
public function dependencies(): array
{
    return [
        ConfigModule::class,
        ViewModule::class, // ← obligatoire si on expose une extension Twig
    ];
}

public function register(Container $container): void
{
    $container->set(MonService::class, fn(Container $c) => new MonService($c));

    $container->set(
        MonSousSystèmeViewExtension::class,
        fn(Container $c) => new MonSousSystèmeViewExtension($c->get(MonService::class))
    );

    // Tag obligatoire pour que ViewModule découvre l'extension
    $container->tag(MonSousSystèmeViewExtension::class, 'twig.extension');
}
```

### Utilisation dans un template Twig

```twig
{# Fonction #}
{{ mon_helper('valeur') }}
{{ mon_autre_helper(4) }}

{# Filtre #}
{{ 'texte'|mon_filtre }}
```

---

## Écrire les tests

Chaque module doit avoir un dossier `Tests/` avec un `phpunit.xml` et au moins un fichier `*Test.php`.

### Structure attendue

```
neo/Core/MonSousSystème/
├── MonSousSystèmeModule.php
├── MonService.php
└── Tests/
    ├── phpunit.xml
    ├── MonServiceTest.php
    └── Fixture/           ← classes de support utilisées uniquement dans les tests
        └── ...
```

### phpunit.xml

Copier ce template en adaptant le nom de la suite (`name`) :

```xml
<?xml version="1.0" encoding="UTF-8"?>
<phpunit xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
         xsi:noNamespaceSchemaLocation="../../../../vendor/phpunit/phpunit/phpunit.xsd"
         bootstrap="../../../../vendor/autoload.php"
         colors="true"
         cacheDirectory=".phpunit.cache"
         failOnWarning="true"
         failOnDeprecation="true"
         displayDetailsOnTestsThatTriggerDeprecations="true"
         displayDetailsOnTestsThatTriggerWarnings="true">

    <testsuites>
        <testsuite name="MonSousSystème">
            <directory suffix="Test.php">.</directory>
        </testsuite>
    </testsuites>

    <source>
        <include>
            <directory suffix=".php">..</directory>
        </include>
        <exclude>
            <directory>.</directory>
        </exclude>
    </source>
</phpunit>
```

> **Note sur les chemins** : le chemin vers `phpunit.xsd` et `autoload.php` remonte de 4 niveaux (`../../../../`) car le fichier vit dans `neo/Core/<Sous-système>/Tests/`. Si votre dossier `Tests/` est plus profond (ex : `neo/Core/Security/Auth/Tests/`), adapter le nombre de `../`.

### Écrire un test

```php
<?php
declare(strict_types=1);

namespace Neo\Core\MonSousSystème\Tests;

use Neo\Core\DI\Container;
use Neo\Core\MonSousSystème\MonService;
use Neo\Core\MonSousSystème\MonSousSystèmeModule;
use PHPUnit\Framework\TestCase;

final class MonServiceTest extends TestCase
{
    private MonService $service;

    protected function setUp(): void
    {
        $container = new Container();
        $module = new MonSousSystèmeModule();
        $module->register($container);

        $this->service = $container->get(MonService::class);
    }

    public function testDoSomethingReturnsExpectedResult(): void
    {
        self::assertSame('résultat', $this->service->doSomething());
    }
}
```

### Tester l'intégration avec le ModuleManager

Quand le module a des dépendances ou modifie l'état global, tester son cycle de vie complet :

```php
public function testModuleRegistersService(): void
{
    $container = new Container();
    $manager = new ModuleManager($container);
    $manager->discover(__DIR__ . '/../..'); // pointe vers neo/Core/MonSousSystème
    $manager->boot();

    self::assertTrue($container->has(MonService::class));
}
```

### Tester une extension de contrôleur

```php
public function testControllerExtensionRegistersMethod(): void
{
    $container = new Container();
    $container->set(MonService::class, fn() => new MonService($container));

    // AbstractController instancie automatiquement les extensions au construct
    $controller = new class($container) extends AbstractController {};

    // On vérifie que la méthode est accessible
    self::assertInstanceOf(MonService::class, $controller->getMonService());
}
```

### Tester une extension Twig

```php
public function testViewExtensionExposesFunctions(): void
{
    $service = new MonService(new Container());
    $ext = new MonSousSystèmeViewExtension($service);

    self::assertArrayHasKey('mon_helper', $ext->getFunctions());
    self::assertArrayHasKey('mon_filtre', $ext->getFilters());
}
```

### Fixtures de test

Les classes utilisées **uniquement** dans les tests (mocks, stubs, scénarios d'erreur) doivent vivre dans `Tests/Fixture/`. Le `ModuleManager` les exclut automatiquement de la découverte grâce au filtre sur les namespaces `\Tests\` et `\Fixture\`.

---

## Valider avant PR

Le script `runner_dev.sh` à la racine du projet est l'unique point d'entrée pour valider un module avant d'ouvrir une PR. Il enchaîne :

1. **Tests PHPUnit** — découverte de tous les `phpunit.xml` sous `neo/Core/*/Tests/`
2. **Analyse statique PHPStan** — niveau 6 sur `neo/`
3. **Récapitulatif** — verdict `Oui / Non` pour l'ouverture de la PR

### Lancer la validation

```bash
bash runner_dev.sh
```

### Conditions pour ouvrir une PR

Le script affiche **"Oui, vous pouvez ouvrir la PR"** et retourne le code 0 uniquement si :

- au moins une suite de tests est découverte (`phpunit.xml` présent)
- toutes les suites passent sans erreur ni warning
- PHPStan s'exécute sans erreur

Dans tous les autres cas, le script retourne le code 1 et détaille la cause d'échec.

### Configuration PHPStan

Le fichier `phpstan.neon` à la racine analyse `neo/` au **niveau 6** :

```
Niveau 6 : vérifie les types de retour, les types de paramètres,
           les propriétés non initialisées, les méthodes inexistantes, etc.
```

Identifiants ignorés (déjà configurés dans `phpstan.neon`) :

| Identifiant | Raison |
|-------------|--------|
| `constant.notFound` | Constantes définies dynamiquement |
| `property.protected` | Pattern extension de contrôleur |
| `method.notFound` | Méthodes enregistrées dynamiquement via `registerMethod()` |
| `constructor.unusedParameter` | Certains constructeurs d'attributs |
| `new.static` | Héritage statique |
| `nullCoalesce.variable` | Variables optionnelles |
| `attribute.abstract` | Attributs sur classes abstraites |

Si PHPStan remonte une erreur légitime, la corriger. Ne pas ajouter d'`ignoreErrors` sans justification documentée.

### Checklist avant PR

```
[ ] Le dossier du module est dans neo/Core/<MonSousSystème>/
[ ] Le fichier module se termine par Module.php
[ ] Le module étend AbstractModule ou implémente ModuleInterface
[ ] Les dépendances sont déclarées dans dependencies()
[ ] Les services sont enregistrés via des factory closures dans register()
[ ] Si extension Twig : ViewModule::class est dans dependencies() et le tag 'twig.extension' est posé
[ ] Si extension contrôleur : le fichier se termine par ControllerExtension.php
[ ] Un dossier Tests/ existe avec phpunit.xml et au moins un *Test.php
[ ] bash runner_dev.sh retourne le code 0
```
