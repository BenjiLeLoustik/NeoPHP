# Cache

The `Cache` submodule provides a caching system with several interchangeable drivers via a common interface.

---

## Summary

1. [Structure](#structure)
2. [Configuration](#configuration)
3. [CacheManager](#cachemanager)
4. [Available Drivers](#available-drivers)
5. [CacheDriverInterface](#cachedriverinterface)
6. [Controller Extension](#controller-extension)
7. [CLI Command](#cli-command)

---

## Structure

```
Cache/
├── CacheManager.php                    # Cache entry point
├── CacheModule.php                     # DI registration
├── Driver/
│   ├── Interface/
│   │   └── CacheDriverInterface.php    # Driver contract
│   ├── FileDriver.php                  # File-based cache
│   ├── RedisDriver.php                 # Redis cache (Predis)
│   └── ArrayDriver.php                 # In-memory cache (tests)
├── Commands/
│   └── CacheClearCommand.php
├── Exception/
│   └── CacheException.php
└── Extension/
    └── CacheControllerExtension.php    # Injects getCache() into controllers
```

---

## Configuration

**File:** `src/<Project>/Config/cache.config.php`

```php
return [
    'driver' => 'files',   // 'files', 'redis', or 'array'
    'ttl'    => 3600,      // Default TTL in seconds

    'drivers' => [
        'files' => [
            'path' => 'cache',   // Relative to storagePath
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

**File:** `CacheManager.php`

Single entry point for the cache. Instantiates the driver according to the configuration and delegates all operations.

```php
$cache = $container->get(CacheManager::class);

// Store a value (TTL in seconds, optional)
$cache->set('user:1', $userData, 3600);

// Read a value (returns $default if missing or expired)
$user = $cache->get('user:1', null);

// Check existence
if ($cache->has('user:1')) { /* ... */ }

// Delete an entry
$cache->delete('user:1');

// Clear the entire cache
$cache->clear();

// One-line cache-aside pattern
$users = $cache->remember('all_users', 600, function () use ($em) {
    return $em->getRepository(User::class)->findAll();
});
```

### `remember` Pattern

```php
// Verbose equivalent
$key = 'stats:daily';
if (!$cache->has($key)) {
    $value = computeExpensiveStats();
    $cache->set($key, $value, 900);
} else {
    $value = $cache->get($key);
}

// With remember
$value = $cache->remember('stats:daily', 900, fn() => computeExpensiveStats());
```

---

## Available Drivers

### FileDriver

**File:** `Driver/FileDriver.php`

Stores data in serialized files. Each key is hashed with SHA-256 for the filename.

- Path: `{storagePath}/cache/`
- Format: `serialize(['key' => ..., 'expires_at' => ..., 'content' => ...])`
- Expiration checked on every read (`time() > $data['expires_at']`)
- `unserialize` with `['allowed_classes' => false]`

### RedisDriver

**File:** `Driver/RedisDriver.php`

Uses **Predis** for the Redis connection.

- TTL natively handled by Redis via `SETEX`
- Keys are prefixed if `prefix` is defined
- `clear()` with a prefix only removes matching keys; without a prefix, it runs `FLUSHDB`
- `unserialize` with `['allowed_classes' => true]`

> **Security:** In a shared Redis environment, always set a unique `prefix` per application to avoid key collisions.

### ArrayDriver

**File:** `Driver/ArrayDriver.php`

In-memory cache (current process only). Data is lost at the end of the request. Ideal for tests.

```php
return ['driver' => 'array', 'ttl' => 3600];
```

---

## CacheDriverInterface

**File:** `Driver/Interface/CacheDriverInterface.php`

Contract that every driver must implement:

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

**Creating a Custom Driver:**

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

## Controller Extension

**File:** `Extension/CacheControllerExtension.php`

Automatically injects `getCache()` into all controllers.

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

## CLI Command

| Command | Description |
|----------|-------------|
| `cache:clear` | Clears a project's cache directory |

```bash
php bin/neo cache:clear --project=Blog
# Removes all files under src/Blog/Storage/var/cache/
```