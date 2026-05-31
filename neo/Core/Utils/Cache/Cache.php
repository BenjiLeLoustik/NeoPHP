<?php
declare(strict_types=1);

namespace Neo\Core\Utils\Cache;

use Neo\Core\DI\Container;
use Neo\Core\Utils\Cache\Driver\ArrayDriver;
use Neo\Core\Utils\Cache\Driver\FileDriver;
use Neo\Core\Utils\Cache\Driver\Interface\CacheDriverInterface;
use Neo\Core\Utils\Cache\Driver\RedisDriver;
use Neo\Core\Utils\Cache\Exception\CacheException;
use Neo\Core\Utils\Config\Config;

class Cache
{
    private CacheDriverInterface $driver;

    public function __construct(Container $container)
    {
        $config = $container->get(Config::class)->from('cache')->all();
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
                title: 'Cache Driver Error',
                message: sprintf("Unsupported cache driver '%s'.", $driverName),
                code: 500
            ),
        };
    }

    public function get(string $key, mixed $default = null): mixed
    {
        return $this->driver->get($key, $default);
    }

    public function set(string $key, mixed $value, ?int $ttl = null): void
    {
        $this->driver->set($key, $value, $ttl);
    }

    public function delete(string $key): void
    {
        $this->driver->delete($key);
    }

    public function clear(): void
    {
        $this->driver->clear();
    }

    public function has(string $key): bool
    {
        return $this->driver->has($key);
    }

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