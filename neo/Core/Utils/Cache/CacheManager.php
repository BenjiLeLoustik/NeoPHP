<?php
declare(strict_types=1);

namespace Neo\Core\Utils\Cache;

use Neo\Core\DI\Container;
use Neo\Core\DI\Exception\ContainerException;
use Neo\Core\Utils\Cache\Driver\ArrayDriver;
use Neo\Core\Utils\Cache\Driver\FileDriver;
use Neo\Core\Utils\Cache\Driver\Interface\CacheDriverInterface;
use Neo\Core\Utils\Cache\Driver\RedisDriver;
use Neo\Core\Utils\Cache\Exception\CacheException;

class CacheManager
{
    private CacheDriverInterface $driver;

    /**
     * @throws CacheException
     * @throws ContainerException
     */
    public function __construct(Container $container)
    {
        $config = $container->get('cache.configModule')->from('cache')->all();
        $driverName = $config['driver'] ?? 'files';
        $ttl = (int) ($config['ttl'] ?? 3600);
        $drivers = $config['drivers'] ?? [];

        $this->driver = match ($driverName) {
            'files' => new FileDriver(
                $container->get('storagePath') . '/' . ($drivers['files']['path'] ?? 'cache'),
                $ttl
            ),
            'redis' => new RedisDriver(
                $drivers['redis'] ?? [],
                $ttl
            ),
            'array' => new ArrayDriver($ttl),
            default => throw new CacheException(
                title: 'CacheManager Driver Error',
                message: sprintf("Unsupported cache driver '%s'.", $driverName),
                code: 500
            ),
        };
    }

    /**
     * @throws CacheException
     */
    public function get(string $key, mixed $default = null): mixed
    {
        return $this->driver->get($key, $default);
    }

    /**
     * @throws CacheException
     */
    public function set(string $key, mixed $value, ?int $ttl = null): void
    {
        $this->driver->set($key, $value, $ttl);
    }

    /**
     * @throws CacheException
     */
    public function delete(string $key): void
    {
        $this->driver->delete($key);
    }

    /**
     * @throws CacheException
     */
    public function clear(): void
    {
        $this->driver->clear();
    }

    /**
     * @throws CacheException
     */
    public function has(string $key): bool
    {
        return $this->driver->has($key);
    }

    /**
     * @throws CacheException
     */
    public function remember(string $key, int $ttl, callable $callback): mixed
    {
        if ($this->driver->has($key)) {
            return $this->driver->get($key);
        }

        $value = $callback();
        $this->driver->set($key, $value, $ttl);

        return $value;
    }

    public function getDriver(): CacheDriverInterface
    {
        return $this->driver;
    }
}