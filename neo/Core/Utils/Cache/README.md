# Cache

Le sous-module `Cache` fournit un système de cache avec plusieurs drivers interchangeables via une interface commune.

---

## Sommaire

1. [Structure](#structure)
2. [Configuration](#configuration)
3. [CacheManager](#cachemanager)
4. [Drivers disponibles](#drivers-disponibles)
5. [CacheDriverInterface](#cachedriverinterface)
6. [Extension contrôleur](#extension-contrôleur)
7. [Commande CLI](#commande-cli)

---

## Structure

```
Cache/
├── CacheManager.php                    # Point d'entrée du cache
├── CacheModule.php                     # Enregistrement DI
├── Driver/
│   ├── Interface/
│   │   └── CacheDriverInterface.php    # Contrat des drivers
│   ├── FileDriver.php                  # Cache fichiers
│   ├── RedisDriver.php                 # Cache Redis (Predis)
│   └── ArrayDriver.php                 # Cache en mémoire (tests)
├── Commands/
│   └── CacheClearCommand.php
├── Exception/
│   └── CacheException.php
└── Extension/
    └── CacheControllerExtension.php    # Injecte getCache() dans les contrôleurs
```

---

## Configuration

**Fichier :** `src/<Projet>/Config/cache.config.php`

```php
return [
    'driver' => 'files',   // 'files', 'redis' ou 'array'
    'ttl'    => 3600,      // TTL par défaut en secondes

    'drivers' => [
        'files' => [
            'path' => 'cache',   // Relatif au storagePath
        ],
        'redis' => [
            'host'     => '127.0.0.1',
            'port'     => 6379,
            'password' => null,
            'database' => 0,
            'prefix'   => 'myapp:',
        ],
    ],
];
```

---

## CacheManager

**Fichier :** `CacheManager.php`

Point d'entrée unique pour le cache. Instancie le driver selon la configuration et délègue toutes les opérations.

```php
$cache = $container->get(CacheManager::class);

// Stocker une valeur (TTL en secondes, optionnel)
$cache->set('user:1', $userData, 3600);

// Lire une valeur (retourne $default si absente ou expirée)
$user = $cache->get('user:1', null);

// Vérifier l'existence
if ($cache->has('user:1')) { /* ... */ }

// Supprimer une entrée
$cache->delete('user:1');

// Vider tout le cache
$cache->clear();

// Cache-aside en une ligne
$users = $cache->remember('all_users', 600, function () use ($em) {
    return $em->getRepository(User::class)->findAll();
});
```

### Pattern `remember`

```php
// Équivalent verbeux
$key = 'stats:daily';
if (!$cache->has($key)) {
    $value = computeExpensiveStats();
    $cache->set($key, $value, 900);
} else {
    $value = $cache->get($key);
}

// Avec remember
$value = $cache->remember('stats:daily', 900, fn() => computeExpensiveStats());
```

---

## Drivers disponibles

### FileDriver

**Fichier :** `Driver/FileDriver.php`

Stocke les données dans des fichiers sérialisés. Chaque clé est hachée en SHA-256 pour le nom de fichier.

- Chemin : `{storagePath}/cache/`
- Format : `serialize(['key' => ..., 'expires_at' => ..., 'content' => ...])`
- Expiration vérifiée à chaque lecture (`time() > $data['expires_at']`)
- `unserialize` avec `['allowed_classes' => false]`

### RedisDriver

**Fichier :** `Driver/RedisDriver.php`

Utilise **Predis** pour la connexion Redis.

- TTL géré nativement par Redis via `SETEX`
- Les clés sont préfixées si `prefix` est défini
- `clear()` avec un préfixe supprime uniquement les clés correspondantes ; sans préfixe, exécute `FLUSHDB`
- `unserialize` avec `['allowed_classes' => true]`

> **Sécurité :** En environnement Redis partagé, toujours définir un `prefix` unique par application pour éviter les collisions de clés.

### ArrayDriver

**Fichier :** `Driver/ArrayDriver.php`

Cache en mémoire (processus courant uniquement). Les données sont perdues à la fin de la requête. Idéal pour les tests.

```php
return ['driver' => 'array', 'ttl' => 3600];
```

---

## CacheDriverInterface

**Fichier :** `Driver/Interface/CacheDriverInterface.php`

Contrat que tout driver doit implémenter :

```php
interface CacheDriverInterface
{
    public function get(string $key, mixed $default = null): mixed;
    public function set(string $key, mixed $value, ?int $ttl = null): void;
    public function delete(string $key): void;
    public function clear(): void;
    public function has(string $key): bool;
}
```

**Créer un driver personnalisé :**

```php
use Neo\Core\Utils\Cache\Driver\Interface\CacheDriverInterface;

final class MemcachedDriver implements CacheDriverInterface
{
    public function __construct(private \Memcached $client) {}

    public function get(string $key, mixed $default = null): mixed
    {
        $value = $this->client->get($key);
        return $value === false ? $default : $value;
    }

    public function set(string $key, mixed $value, ?int $ttl = null): void
    {
        $this->client->set($key, $value, $ttl ?? 3600);
    }

    public function delete(string $key): void { $this->client->delete($key); }
    public function clear(): void             { $this->client->flush(); }

    public function has(string $key): bool
    {
        $this->client->get($key);
        return $this->client->getResultCode() !== \Memcached::RES_NOTFOUND;
    }
}
```

---

## Extension contrôleur

**Fichier :** `Extension/CacheControllerExtension.php`

Injecte automatiquement `getCache()` dans tous les contrôleurs.

```php
class ProductController extends AbstractController
{
    #[Route('/products', 'GET')]
    public function index(): Response
    {
        $products = $this->getCache()->remember('products:all', 300, function () {
            return $this->getRepository(Product::class)->findAll();
        });

        return $this->render('products/index.html.twig', ['products' => $products]);
    }
}
```

---

## Commande CLI

| Commande | Description |
|----------|-------------|
| `cache:clear` | Vide le répertoire de cache d'un projet |

```bash
php bin/neo cache:clear --project=Blog
# Supprime tous les fichiers dans src/Blog/Storage/var/cache/
```
