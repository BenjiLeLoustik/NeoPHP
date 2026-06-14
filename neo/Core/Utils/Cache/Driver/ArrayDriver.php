<?php
declare(strict_types=1);

namespace Neo\Core\Utils\Cache\Driver;

use Neo\Core\Utils\Cache\Driver\Interface\CacheDriverInterface;

class ArrayDriver implements CacheDriverInterface
{
    /** @var array<string, array{content: mixed, expires_at: int}> */
    private array $store = [];

    private int $defaultTtl;

    public function __construct(int $defaultTtl = 3600)
    {
        $this->defaultTtl = $defaultTtl;
    }

    public function get(string $key, mixed $default = null): mixed
    {
        if (!$this->has($key)) {
            return $default;
        }

        return $this->store[$key]['content'];
    }

    public function set(string $key, mixed $value, ?int $ttl = null): void
    {
        $this->store[$key] = [
            'content' => $value,
            'expires_at' => time() + ($ttl ?? $this->defaultTtl),
        ];
    }

    public function delete(string $key): void
    {
        unset($this->store[$key]);
    }

    public function clear(): void
    {
        $this->store = [];
    }

    public function has(string $key): bool
    {
        if (!isset($this->store[$key])) {
            return false;
        }

        if (time() > $this->store[$key]['expires_at']) {
            $this->delete($key);
            return false;
        }

        return true;
    }
}