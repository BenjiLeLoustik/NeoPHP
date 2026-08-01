# Module

Le sous-système `Module` est le point d'entrée de toute fonctionnalité du framework NeoPHP. Il définit un contrat uniforme (`ModuleInterface`) que chaque composant doit respecter, et fournit un gestionnaire (`ModuleManager`) capable de découvrir, ordonner et initialiser automatiquement tous les modules présents dans le projet.

---

## Sommaire

1. [Concepts fondamentaux](#concepts-fondamentaux)
2. [ModuleInterface](#moduleinterface)
3. [ModuleManager](#modulemanager)
4. [ModuleException](#moduleexception)
5. [Créer un module personnalisé](#créer-un-module-personnalisé)
6. [Cycle de vie d'un module](#cycle-de-vie-dun-module)
7. [Résolution des dépendances](#résolution-des-dépendances)

---

## Concepts fondamentaux

Un **module** dans NeoPHP est une classe dont le nom se termine par `Module.php` et qui implémente `ModuleInterface`. Le framework parcourt récursivement un répertoire de base à la recherche de ces classes, les charge dans le bon ordre selon leurs dépendances, puis les initialise dans le conteneur d'injection de dépendances.

Les modules jouent trois rôles :

- **Découverte** : le `ModuleManager` trouve automatiquement tous les modules via la réflexion PHP.
- **Enregistrement** : chaque module déclare ses liaisons dans le conteneur DI via `register()`.
- **Initialisation** : chaque module retourne un objet "manager" via `init()` qui est ensuite disponible dans le conteneur sous l'alias `<nom>.manager`.

---

## ModuleInterface

Fichier : `Interface/ModuleInterface.php`

```php
namespace Neo\Core\Module\Interface;

use Neo\Core\DI\Container;

interface ModuleInterface
{
    /**
     * Retourne les classes de modules dont ce module dépend.
     * @return array<class-string>
     */
    public function dependencies(): array;

    /**
     * Enregistre les liaisons dans le conteneur DI (appelé avant init).
     */
    public function register(Container $container): void;

    /**
     * Initialise le module et retourne son objet principal (manager, service...).
     */
    public function init(Container $container): object;
}
```

### Contrat

| Méthode | Rôle | Moment d'appel |
|---|---|---|
| `dependencies()` | Déclare les modules prérequis | Avant `register()` et `init()` |
| `register()` | Lie les classes au conteneur DI | Avant `init()` |
| `init()` | Initialise et retourne le manager | Après `register()` de tous les modules ordonnés |

---

## ModuleManager

Fichier : `ModuleManager.php`

### Découverte automatique : `discover()`

```php
$manager = new ModuleManager($container);
$manager->discover('/chemin/vers/neo/Core');
```

La méthode `discover()` parcourt récursivement le répertoire fourni. Elle sélectionne uniquement les fichiers dont le nom se termine par `Module.php`, extrait le FQCN (namespace + classe) via des expressions régulières sur le code source, vérifie que la classe implémente bien `ModuleInterface`, et exclut par défaut les classes situées dans des namespaces `\Tests\` ou `\Fixture\`.

```php
// Exclure les fixtures de test (comportement par défaut)
$manager->discover($basePath, excludeTestFixtures: true);

// Inclure tout (utile pour les tests)
$manager->discover($basePath, excludeTestFixtures: false);
```

### Démarrage : `boot()`

```php
$manager->boot();
```

La méthode `boot()` :

1. Calcule l'ordre topologique des modules via `resolveDependencyOrder()`.
2. Pour chaque module (dans l'ordre) :
   - Instancie la classe du module.
   - Appelle `register($container)`.
   - Injecte les résultats des modules dépendants comme sous-clés dans le conteneur (ex. `router.configModule`).
   - Appelle `init($container)` et récupère le résultat.
   - Enregistre le résultat dans le conteneur sous `<alias>.manager` (ex. `router.manager`).

### Dérivation de l'alias

Le `ModuleManager` dérive automatiquement un alias court à partir du nom de la classe. Le suffixe `Module` est supprimé et la première lettre est mise en minuscule :

| Classe | Alias |
|---|---|
| `RouterModule` | `router` |
| `ProfilerModule` | `profiler` |
| `ConfigModule` | `config` |

---

## ModuleException

Fichier : `Exception/ModuleException.php`

```php
namespace Neo\Core\Module\Exception;

use Neo\Core\Error\Exception\FrameworkException;

class ModuleException extends FrameworkException {}
```

`ModuleException` étend `FrameworkException` et est levée dans deux situations :

**Dépendance circulaire :**
```
Circular dependency detected in module "App\RouterModule".
Code HTTP : 500
Contexte : ['module' => '...', 'chain' => [...]]
```

**Module introuvable :**
```
Module 'App\MissingModule' is missing but is required by 'App\RouterModule'.
Make sure it is present in neo/Core and correctly loaded.
Code HTTP : 500
Contexte : ['missing' => '...', 'requiredBy' => '...']
```

---

## Créer un module personnalisé

Voici un exemple complet de module qui enregistre un service `PaymentService` dans le conteneur :

```php
<?php
declare(strict_types=1);

namespace App\Payment;

use Neo\Core\DI\Container;
use Neo\Core\Module\Interface\ModuleInterface;
use Neo\Core\Utils\Config\ConfigModule;

class PaymentModule implements ModuleInterface
{
    public function dependencies(): array
    {
        // Ce module nécessite que ConfigModule soit initialisé avant lui
        return [
            ConfigModule::class,
        ];
    }

    public function register(Container $container): void
    {
        // Enregistrement paresseux : la factory n'est appelée qu'au premier get()
        $container->set(PaymentService::class, fn(Container $c) => new PaymentService(
            $c->get('payment.configModule')->from('payment')->all()
        ));
    }

    public function init(Container $container): object
    {
        // Retourner l'objet principal du module
        // Il sera accessible via $container->get('payment.manager')
        return $container->get(PaymentService::class);
    }
}
```

### Convention de nommage

- Le fichier doit s'appeler `PaymentModule.php`.
- La classe doit implémenter `ModuleInterface`.
- Le nom doit se terminer par `Module`.

---

## Cycle de vie d'un module

```
discover()
    ├── Scan récursif du répertoire
    ├── Filtre : nom se termine par "Module.php"
    ├── Extraction FQCN par regex
    ├── Vérification : implements ModuleInterface
    └── Ajout dans $this->modules[]

boot()
    ├── resolveDependencyOrder() → tri topologique
    └── Pour chaque module (dans l'ordre) :
        ├── new $moduleClass()
        ├── $module->register($container)
        ├── Injection des résultats des dépendances
        │     ex. $container->set('router.configModule', $configResult)
        ├── $result = $module->init($container)
        └── $container->set('router.manager', $result)
```

---

## Résolution des dépendances

Le `ModuleManager` utilise un algorithme de tri topologique (DFS - Depth-First Search) pour déterminer l'ordre d'initialisation. Il détecte automatiquement les dépendances circulaires.

**Exemple avec dépendances imbriquées :**

```
ProfilerModule
    ├── ResponseModule
    ├── EventModule
    ├── RouterModule
    │     ├── ConfigModule   ← partagé
    │     └── ViewModule
    ├── AuthModule
    ├── TranslationModule
    └── ConfigModule         ← déjà résolu, ignoré
```

Ordre d'initialisation produit :
1. `ConfigModule`
2. `ViewModule`
3. `RouterModule`
4. `ResponseModule`
5. `EventModule`
6. `AuthModule`
7. `TranslationModule`
8. `ProfilerModule`

Chaque module n'est initialisé qu'une seule fois, même s'il apparaît dans plusieurs arbres de dépendances.
