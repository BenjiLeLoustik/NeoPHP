<?php
declare(strict_types=1);

namespace Neo\Core\Utils\Cache;

use Neo\Core\DI\Container;
use Neo\Core\DI\Exception\ContainerException;
use Neo\Core\Profiler\TimelineRecorder;
use Neo\Core\Utils\Cache\Driver\ArrayDriver;
use Neo\Core\Utils\Cache\Driver\FileDriver;
use Neo\Core\Utils\Cache\Driver\Interface\CacheDriverInterface;
use Neo\Core\Utils\Cache\Driver\RedisDriver;
use Neo\Core\Utils\Cache\Exception\CacheException;

class CacheManager
{
    private CacheDriverInterface $driver;
    private string $driverName;

    /** @var list<array{
     *     action: string,
     *     key: string,
     *     hit: bool|null,
     *     value: string|null,
     *     ttl: int|null,
     *     duration: float
     * }>
     */
    private static array $log = [];

    /**
     * @throws CacheException
     * @throws ContainerException
     * @throws \ReflectionException
     */
    public function __construct(Container $container)
    {
        $config = $container->get('cache.configModule')->from('cache')->all();
        $driverName = $config['driver'] ?? 'files';
        $ttl = (int) ($config['ttl'] ?? 3600);
        $drivers = $config['drivers'] ?? [];

        $this->driverName = $driverName;

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
        $start = microtime(true);
        $hit = $this->driver->has($key);
        $value = $this->driver->get($key, $default);

        self::$log[] = [
            'action' => 'get',
            'key' => $key,
            'hit' => $hit,
            'value' => $this->stringify($value),
            'ttl' => null,
            'duration' => round((microtime(true) - $start) * 1000, 2),
        ];

        if (class_exists(TimelineRecorder::class)) {
            TimelineRecorder::record('cache', $this->driverName . ':' . $key, $start);
        }

        return $value;
    }

    /**
     * @throws CacheException
     */
    public function set(string $key, mixed $value, ?int $ttl = null): void
    {
        $start = microtime(true);
        $this->driver->set($key, $value, $ttl);

        self::$log[] = [
            'action' => 'set',
            'key' => $key,
            'hit' => null,
            'value' => $this->stringify($value),
            'ttl' => $ttl,
            'duration' => round((microtime(true) - $start) * 1000, 2),
        ];

        if (class_exists(TimelineRecorder::class)) {
            TimelineRecorder::record('cache', $this->driverName . ':' . $key, $start);
        }
    }

    /**
     * @throws CacheException
     */
    public function delete(string $key): void
    {
        $start = microtime(true);
        $this->driver->delete($key);

        self::$log[] = [
            'action' => 'delete',
            'key' => $key,
            'hit' => null,
            'value' => null,
            'ttl' => null,
            'duration' => round((microtime(true) - $start) * 1000, 2),
        ];

        if (class_exists(TimelineRecorder::class)) {
            TimelineRecorder::record('cache', $this->driverName . ':' . $key, $start);
        }
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
        $start = microtime(true);
        $hit = $this->driver->has($key);

        self::$log[] = [
            'action' => 'has',
            'key' => $key,
            'hit' => $hit,
            'value' => null,
            'ttl' => null,
            'duration' => round((microtime(true) - $start) * 1000, 2),
        ];

        if (class_exists(TimelineRecorder::class)) {
            TimelineRecorder::record('cache', $this->driverName . ':' . $key, $start);
        }

        return $hit;
    }

    /**
     * @throws CacheException
     */
    public function remember(string $key, int $ttl, callable $callback): mixed
    {
        if ($this->driver->has($key)) {
            return $this->get($key);
        }

        $value = $callback();
        $this->set($key, $value, $ttl);

        return $value;
    }

    public function getDriver(): CacheDriverInterface
    {
        return $this->driver;
    }

    public function getDriverName(): string
    {
        return $this->driverName;
    }

    private function stringify(mixed $value): ?string
    {
        return match (true) {
            $value === null => null,
            is_scalar($value) => (string) $value,
            is_array($value) => json_encode($value, JSON_UNESCAPED_UNICODE) ?: '[]',
            default => get_debug_type($value),
        };
    }

    /**
     * @return list<array{action: string, key: string, hit: bool|null, value: string|null, ttl: int|null, duration: float}>
     */
    public static function getLog(): array
    {
        return self::$log;
    }
}