<?php
declare(strict_types=1);

namespace Neo\Core\Utils\Cache\Driver;

use Neo\Core\Utils\Cache\Driver\Interface\CacheDriverInterface;
use Neo\Core\Utils\Cache\Exception\CacheException;
use Redis;

class RedisDriver implements CacheDriverInterface
{
    private Redis $redis;
    private int $defaultTtl;
    private string $prefix;

    public function __construct(array $config, int $defaultTtl = 3600)
    {
        $this->defaultTtl = $defaultTtl;
        $this->prefix = $config['prefix'] ?? '';

        $this->redis = new Redis();

        $connected = $this->redis->connect(
            $config['host'] ?? '127.0.0.1',
            (int) ($config['port'] ?? 6379),
            2.5
        );

        if (!$connected) {
            throw new CacheException(
                title: 'Redis Connection Error',
                message: 'Unable to connect to Redis server.',
                code: 500
            );
        }

        if (!empty($config['password'])) {
            $this->redis->auth($config['password']);
        }

        if (isset($config['database'])) {
            $this->redis->select((int) $config['database']);
        }
    }

    public function get(string $key, mixed $default = null): mixed
    {
        $raw = $this->redis->get($this->key($key));

        if ($raw === false) {
            return $default;
        }

        $data = unserialize($raw, ['allowed_classes' => true]);

        if (!is_array($data) || !isset($data['content'])) {
            return $default;
        }

        return $data['content'];
    }

    public function set(string $key, mixed $value, ?int $ttl = null): void
    {
        $data = serialize(['content' => $value]);
        $ttl = $ttl ?? $this->defaultTtl;

        $result = $ttl > 0
            ? $this->redis->setex($this->key($key), $ttl, $data)
            : $this->redis->set($this->key($key), $data);

        if (!$result) {
            throw new CacheException(
                title: 'Cache Write Error',
                message: sprintf("Unable to write cache for key '%s'.", $key),
                code: 500
            );
        }
    }

    public function delete(string $key): void
    {
        $this->redis->del($this->key($key));
    }

    public function clear(): void
    {
        if ($this->prefix !== '') {
            $keys = $this->redis->keys($this->prefix . '*');
            if (!empty($keys)) {
                $this->redis->del(...$keys);
            }
            return;
        }

        $this->redis->flushDB();
    }

    public function has(string $key): bool
    {
        return (bool) $this->redis->exists($this->key($key));
    }

    private function key(string $key): string
    {
        return $this->prefix . $key;
    }
}