# Scanner

Le sous-module `Scanner` fournit un outil de réflexion pour découvrir et lire les attributs PHP 8 sur une classe, ses méthodes, ses propriétés et les paramètres de ses méthodes.

---

## Sommaire

1. [Structure](#structure)
2. [ScannerAttributeManager](#scannerattributemanager)
3. [Configuration du scan](#configuration-du-scan)
4. [AttributeScanResult](#attributescanresult)
5. [Cas d'usage](#cas-dusage)

---

## Structure

```
Scanner/
├── ScannerAttributeManager.php         # Outil de réflexion sur les attributs PHP
├── AttributeScanResult.php             # DTO représentant une entrée de résultat de scan
├── ScannerModule.php                   # Enregistrement DI
└── Extension/
    └── ScannerControllerExtension.php  # Injecte getScanner() dans les contrôleurs
```

---

## ScannerAttributeManager

**Fichier :** `ScannerAttributeManager.php`

```php
use Neo\Core\Utils\Scanner\ScannerAttributeManager;

$scanner = new ScannerAttributeManager(MyController::class);

$results = $scanner
    ->onClass()           // Scanner la classe elle-même
    ->onMethods()         // Scanner les méthodes
    ->onProperties()      // Scanner les propriétés
    ->onParameters()      // Scanner les paramètres de méthodes
    ->withAttribute(Route::class) // Filtrer par attribut spécifique
    ->scan();
```

`scan()` retourne une `list<AttributeScanResult>`.

---

## Configuration du scan

### Portée

| Méthode | Cible |
|---------|-------|
| `onClass()` | La classe elle-même |
| `onMethods(?int $filter)` | Méthodes (filtre `ReflectionMethod::IS_PUBLIC`, etc.) |
| `onProperties(?int $filter)` | Propriétés |
| `onParameters()` | Paramètres de méthodes |
| `onAll()` | Tout (classe + méthodes + propriétés + paramètres) |

### Filtre d'attribut

```php
// Filtrer par un attribut spécifique
->withAttribute(Route::class)

// Scanner tous les attributs sans filtre
->withAllAttributes()
```

### Exemples

```php
// Méthodes publiques uniquement
$results = (new ScannerAttributeManager(MyController::class))
    ->onMethods(ReflectionMethod::IS_PUBLIC)
    ->withAttribute(Route::class)
    ->scan();

// Propriétés privées et protégées
$results = (new ScannerAttributeManager(MyService::class))
    ->onProperties(ReflectionProperty::IS_PRIVATE | ReflectionProperty::IS_PROTECTED)
    ->withAttribute(Inject::class)
    ->scan();

// Tout sans filtre d'attribut
$results = (new ScannerAttributeManager(MyClass::class))
    ->onAll()
    ->withAllAttributes()
    ->scan();
```

---

## AttributeScanResult

**Fichier :** `AttributeScanResult.php`

DTO qui remplace le tableau associatif `array{target, attribute, arguments, type, reflection}` autrefois retourné par `scan()`. Chaque entrée de résultat est désormais une instance de cette classe, exposée via des getters :

```php
class AttributeScanResult
{
    public function __construct(
        private string $target,       // Étiquette lisible, ex. 'MyController::index()'
        private object $attribute,    // Instance de l'attribut
        private array $arguments,     // Arguments bruts du constructeur de l'attribut
        private string $type,         // 'class'|'method'|'property'|'parameter'
        private ReflectionClass|ReflectionMethod|ReflectionProperty|ReflectionParameter $reflection,
    ) {}
}
```

Accès via `getTarget()`, `getAttribute()`, `getArguments()`, `getType()`, `getReflection()`. Le type retourné par `getReflection()` dépend de `getType()` : un consommateur qui a besoin d'un `ReflectionMethod` doit vérifier `instanceof ReflectionMethod` avant de l'utiliser (le nom de la classe étant `class`, `method`, `property` ou `parameter`, le type de réflexion associé varie en conséquence).

```php
foreach ($results as $entry) {
    $route = $entry->getAttribute();       // instance de l'attribut, ex. Route
    $reflection = $entry->getReflection(); // ReflectionMethod, ReflectionClass, ...

    if ($reflection instanceof ReflectionMethod) {
        echo $reflection->getName();
    }
}
```

---

## Cas d'usage

### Découverte de routes

```php
$scanner = new ScannerAttributeManager(HomeController::class);
$routes  = $scanner
    ->onMethods(ReflectionMethod::IS_PUBLIC)
    ->withAttribute(Route::class)
    ->scan();

foreach ($routes as $entry) {
    /** @var Route $route */
    $route  = $entry->getAttribute();
    $method = $entry->getReflection(); // ReflectionMethod

    if (!$method instanceof ReflectionMethod) {
        continue;
    }

    echo sprintf('%s %s → %s::%s',
        $route->method,
        $route->path,
        HomeController::class,
        $method->getName()
    );
}
```

### Injection de dépendances via attribut

```php
$scanner = new ScannerAttributeManager(MyService::class);
$injects = $scanner
    ->onProperties(ReflectionProperty::IS_PRIVATE | ReflectionProperty::IS_PROTECTED)
    ->withAttribute(Inject::class)
    ->scan();

foreach ($injects as $entry) {
    $prop = $entry->getReflection();

    if (!$prop instanceof ReflectionProperty) {
        continue;
    }

    /** @var Inject $inject */
    $inject  = $entry->getAttribute();
    $service = $container->get($inject->type);
    $prop->setValue($instance, $service);
}
```

### Performance

Pour les scans fréquents (ex. : découverte de routes au démarrage), il est recommandé de mettre les résultats en cache via `CacheManager` (driver `array` ou `files`) afin d'éviter le coût de la réflexion à chaque requête.