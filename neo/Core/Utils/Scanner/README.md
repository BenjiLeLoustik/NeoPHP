# Scanner

Le sous-module `Scanner` fournit un outil de réflexion pour découvrir et lire les attributs PHP 8 sur une classe, ses méthodes, ses propriétés et les paramètres de ses méthodes.

---

## Sommaire

1. [Structure](#structure)
2. [ScannerAttributeManager](#scannerattributemanager)
3. [Configuration du scan](#configuration-du-scan)
4. [Structure d'un résultat](#structure-dun-résultat)
5. [Cas d'usage](#cas-dusage)

---

## Structure

```
Scanner/
├── ScannerAttributeManager.php         # Outil de réflexion sur les attributs PHP
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

## Structure d'un résultat

Chaque entrée retournée par `scan()` est un tableau associatif :

```php
[
    'target'     => 'MyController::index()',    // Étiquette lisible
    'attribute'  => /* instance de l'attribut */,
    'arguments'  => ['/home', 'GET'],           // Arguments bruts du constructeur
    'type'       => 'method',                   // 'class'|'method'|'property'|'parameter'
    'reflection' => /* ReflectionMethod */,     // Objet de réflexion
]
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
    $route  = $entry['attribute'];
    $method = $entry['reflection']; // ReflectionMethod

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
    /** @var ReflectionProperty $prop */
    $prop    = $entry['reflection'];
    $inject  = $entry['attribute'];
    $service = $container->get($inject->type);
    $prop->setValue($instance, $service);
}
```

### Performance

Pour les scans fréquents (ex. : découverte de routes au démarrage), il est recommandé de mettre les résultats en cache via `CacheManager` (driver `array` ou `files`) afin d'éviter le coût de la réflexion à chaque requête.
