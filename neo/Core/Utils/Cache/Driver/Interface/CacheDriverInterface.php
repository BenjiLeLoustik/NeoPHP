<?php

namespace Neo\Core\Utils\Cache\Driver\Interface;

interface CacheDriverInterface
{
    public function get(string $key, mixed $default = null): mixed;
    public function set(string $key, mixed $value, ?int $ttl = null): void;
    public function delete(string $key): void;
    public function clear(): void;
    public function has(string $key): bool;
}