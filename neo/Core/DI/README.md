# Injection de Dépendances

Le module `DI` (Dependency Injection) est le coeur du framework NeoPHP. Il fournit un conteneur IoC (Inversion of Control) conforme à la spécification PSR-11, capable de résoudre automatiquement les dépendances par réflexion, de gérer des singletons, des bindings d'interfaces, des tags et d'appeler des callables avec auto-wiring.

---

## Fichiers du module

| Fichier | Rôle |
|---|---|
| `Container.php` | Conteneur IoC principal (PSR-11) |
| `ContainerRegistry.php` | Point d'accès global statique au conteneur |
| `Exception/ContainerException.php` | Exception spécifique au conteneur |

---

## Container

`Neo\Core\DI\Container` implémente `Psr\Container\ContainerInterface`.

### Enregistrer un service

#### `set(string $id, mixed $value)` — définition brute ou factory

```php
// Valeur scalaire ou objet direct
$container->set('app.name', 'NeoPHP');

// Factory callable : reçoit le conteneur, exécutée une seule fois (singleton lazy)
$container->set(MyService::class, fn(Container $c) => new MyService($c->get(Dep::class)));
```

#### `singleton(string $id, callable $factory)` — alias sémantique de `set`

```php
$container->singleton(CacheService::class, fn(Container $c) => new CacheService());
```

#### `instance(string $id, object $object)` — enregistrer un objet déjà construit

```php
$container->instance(LoggerInterface::class, $monologInstance);
```

#### `bind(string $abstract, string $concrete)` — lier une interface à une implémentation

```php
$container->bind(LoggerInterface::class, FileLogger::class);
```

---

### Résoudre un service

#### `get(string $id): mixed`

Résout le service selon l'ordre de priorité suivant :

1. Binding d'interface → suit la redirection vers la classe concrète
2. Instance déjà résolue (cache interne)
3. Définition enregistrée (factory ou valeur)
4. Auto-wiring par réflexion si la classe existe

```php
$service = $container->get(MyService::class);
$name    = $container->get('app.name');
```

#### `has(string $id): bool`

Vérifie l'existence du service sans le résoudre.

```php
if ($container->has(CacheService::class)) {
    // ...
}
```

#### `make(string $id, array $parameters = []): object`

Crée toujours une **nouvelle instance** (ignore le cache), avec possibilité de passer des paramètres supplémentaires nommés.

```php
$request = $container->make(Request::class, ['method' => 'POST', 'path' => '/api']);
```

---

### Auto-wiring et résolution par réflexion

Le conteneur utilise `ReflectionClass` pour inspecter le constructeur et injecter automatiquement chaque paramètre :

- Paramètre de type classe non-built-in → résolution récursive via `get()`
- Paramètre nullable non trouvé → `null`
- Paramètre avec valeur par défaut → utilisation de la valeur par défaut
- Sinon → lève une `ContainerException`

```php
class OrderService
{
    public function __construct(
        private readonly PaymentGateway $gateway,
        private readonly Logger $logger,
        private readonly string $currency = 'EUR'
    ) {}
}

// Le conteneur résout PaymentGateway et Logger automatiquement
$service = $container->get(OrderService::class);
```

#### Cas particulier : `AbstractController`

Les contrôleurs héritant de `AbstractController` bénéficient d'un traitement spécial : le conteneur invoque séparément le constructeur parent (qui reçoit le `Container`) puis injecte les dépendances du constructeur enfant directement en propriétés, sans passer par le constructeur normal, pour éviter les conflits de paramètre `Container`.

---

### Détection des dépendances circulaires

Le conteneur maintient un registre `$resolving` pendant la résolution. Si une classe tente de se résoudre elle-même indirectement, une `ContainerException` est levée avec la chaîne complète :

```
Circular dependency detected while resolving 'A'. Chain: A → B → A
```

---

### Appel de callable avec auto-wiring

#### `call(callable $callable, array $extraParams = []): mixed`

Appelle n'importe quel callable en injectant ses dépendances :

```php
$result = $container->call(function (MyService $service, string $name = 'default') {
    return $service->process($name);
});

// Méthode d'instance
$result = $container->call([$controller, 'index']);

// Méthode statique sous forme de chaîne
$result = $container->call('App\Controller\HomeController::index');
```

---

### Tags

Les tags permettent de regrouper plusieurs services sous une étiquette commune et de les récupérer en liste.

```php
// Enregistrer des services avec un tag
$container->set(XmlExporter::class, fn() => new XmlExporter());
$container->set(CsvExporter::class, fn() => new CsvExporter());

$container->tag(XmlExporter::class, 'exporter');
$container->tag(CsvExporter::class, 'exporter');

// Récupérer tous les services tagués
$exporters = $container->tagged('exporter');
// → [XmlExporter instance, CsvExporter instance]
```

---

### Inspection du conteneur

```php
$container->getDefinitions(); // Liste des IDs enregistrés avec set/singleton
$container->getInstances();   // Liste des IDs déjà résolus (cache)
$container->getBindings();    // Tableau abstract → concrete
```

---

## ContainerRegistry

`ContainerRegistry` est un registre statique permettant d'accéder au conteneur depuis n'importe quel endroit du code sans injection explicite. Il est initialisé une seule fois au bootstrap.

```php
use Neo\Core\DI\ContainerRegistry;

// Au bootstrap de l'application
ContainerRegistry::set($container);

// Depuis n'importe où
$container = ContainerRegistry::get();
$service   = ContainerRegistry::get()->get(MyService::class);
```

Si `get()` est appelé avant `set()`, une `ContainerException` est levée :

```
Container Not Registered : Container has not been registered. Call ContainerRegistry::set() during bootstrap.
```

---

## ContainerException

`Neo\Core\DI\Exception\ContainerException` étend `FrameworkException`. Elle est levée dans les cas suivants :

| Code | Titre | Cause |
|---|---|---|
| 404 | Service Not Found | ID inconnu du conteneur |
| 404 | Class Not Found | La classe n'existe pas |
| 422 | Class Not Instantiable | Interface, classe abstraite, etc. |
| 422 | Parameter Cannot Be Resolved | Paramètre scalaire sans valeur par défaut |
| 500 | Circular Dependency | Boucle de dépendances détectée |

---

## Flux de résolution complet

```
get('MyService')
    ├── binding ? → résoudre la classe concrète liée
    ├── instance en cache ? → retourner directement
    ├── définition (factory) ? → appeler la factory, mettre en cache, retourner
    ├── class_exists ? → resolveClass() par réflexion, mettre en cache, retourner
    └── sinon → ContainerException (404)
```
