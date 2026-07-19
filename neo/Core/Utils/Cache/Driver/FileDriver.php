<?php
declare(strict_types=1);

namespace Neo\Core\Utils\Cache\Driver;

use Neo\Core\Utils\Cache\Driver\Interface\CacheDriverInterface;
use Neo\Core\Utils\Cache\Exception\CacheException;

class FileDriver implements CacheDriverInterface
{
    private string $directory;
    private int $defaultTtl;

    /**
     * @throws CacheException
     */
    public function __construct(string $directory, int $defaultTtl = 3600)
    {
        $this->directory = $directory;
        $this->defaultTtl = $defaultTtl;

        if (
            !is_dir($this->directory) && !mkdir($this->directory, 0777, true) &&
            !is_dir($this->directory)
        ) {
            throw new CacheException(
                title: 'Cache Directory Error',
                message: sprintf("Unable to create cache directory '%s'.", $this->directory),
                code: 500
            );
        }
    }

    /**
     * @throws CacheException
     */
    public function get(string $key, mixed $default = null): mixed
    {
        $file = $this->path($key);

        if (!file_exists($file)) {
            return $default;
        }

        $raw = file_get_contents($file);

        if ($raw === false) {
            throw new CacheException(
                title: 'Cache Read Error',
                message: sprintf("Unable to read cache for key '%s'.", $key),
                code: 500
            );
        }

        $data = unserialize($raw, ['allowed_classes' => false]);

        if (!is_array($data) || !isset($data['expires_at'], $data['content'])) {
            $this->delete($key);
            return $default;
        }

        if (time() > $data['expires_at']) {
            $this->delete($key);
            return $default;
        }

        return $data['content'];
    }

    /**
     * @throws CacheException
     */
    public function set(string $key, mixed $value, ?int $ttl = null): void
    {
        $data = [
            'key' => $key,
            'expires_at' => time() + ($ttl ?? $this->defaultTtl),
            'content' => $value,
        ];

        if (file_put_contents($this->path($key), serialize($data), LOCK_EX) === false) {
            throw new CacheException(
                title: 'Cache Write Error',
                message: sprintf("Unable to write cache for key '%s'.", $key),
                code: 500
            );
        }
    }

    /**
     * @throws CacheException
     */
    public function delete(string $key): void
    {
        $file = $this->path($key);

        if (file_exists($file) && !unlink($file)) {
            throw new CacheException(
                title: 'Cache Delete Error',
                message: sprintf("Unable to delete cache for key '%s'.", $key),
                code: 500
            );
        }
    }

    /**
     * @throws CacheException
     */
    public function clear(): void
    {
        $files = glob($this->directory . '/*.cache') ?: [];

        foreach ($files as $file) {
            if (is_file($file) && !unlink($file)) {
                throw new CacheException(
                    title: 'Cache Clear Error',
                    message: sprintf("Unable to delete cache file '%s'.", $file),
                    code: 500
                );
            }
        }
    }

    /**
     * @throws CacheException
     */
    public function has(string $key): bool
    {
        $file = $this->path($key);

        if (!file_exists($file)) {
            return false;
        }

        $raw = file_get_contents($file);
        $data = $raw !== false ? unserialize($raw, ['allowed_classes' => false]) : null;

        if (!is_array($data) || !isset($data['expires_at'])) {
            $this->delete($key);
            return false;
        }

        if (time() > $data['expires_at']) {
            $this->delete($key);
            return false;
        }

        return true;
    }

    private function path(string $key): string
    {
        return $this->directory . '/' . hash('sha256', $key) . '.cache';
    }
}