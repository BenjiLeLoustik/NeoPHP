<?php
declare(strict_types=1);

namespace Neo\Core\Utils\Cache\Driver;

use Neo\Core\Utils\Cache\Driver\Interface\CacheDriverInterface;
use Neo\Core\Utils\Cache\Exception\CacheException;
use Predis\Client;
use Predis\Connection\ConnectionException;

class RedisDriver implements CacheDriverInterface
{
    private Client $redis;
    private int $defaultTtl;
    private string $prefix;

    /**
     * @param array{host?: string, port?: int, password?: string|null, database?: int, prefix?: string} $config
     * @throws CacheException
     */
    public function __construct(array $config, int $defaultTtl = 3600)
    {
        $this->defaultTtl = $defaultTtl;
        $this->prefix = $config['prefix'] ?? '';

        try {
            $this->redis = new Client([
                'scheme' => 'tcp',
                'host' => $config['host'] ?? '127.0.0.1',
                'port' => (int) ($config['port'] ?? 6379),
                'password' => $config['password'] ?? null,
                'database' => (int) ($config['database'] ?? 0),
            ]);

            $this->redis->ping();
        } catch (ConnectionException $e) {
            throw new CacheException(
                title: 'Redis Connection Error',
                message: sprintf('Unable to connect to Redis server: %s', $e->getMessage()),
                code: 500
            );
        }
    }

    public function get(string $key, mixed $default = null): mixed
    {
        $raw = $this->redis->get($this->key($key));

        if ($raw === null) {
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

        if ($ttl > 0) {
            $this->redis->setex($this->key($key), $ttl, $data);
        } else {
            $this->redis->set($this->key($key), $data);
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

        $this->redis->flushdb();
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