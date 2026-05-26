<?php
declare(strict_types=1);

namespace Neo\Core\Utils;

use Neo\Core\DI\Container;
use Neo\Core\Error\Exception\FrameworkException;
use Neo\Core\Utils\Exception\CacheException;

class Cache
{
    private string $cacheDirectory;
    private int $defaultTtl;

    public function __construct(Container $container)
    {
        $config = $container->get(Config::class)->from('cache')->all();
        $this->cacheDirectory = $container->get('storagePath') . '/cache';
        $this->defaultTtl = (int) ($config['ttl'] ?? 3600);

        if (!is_dir($this->cacheDirectory) && !mkdir($this->cacheDirectory, 0777, true) && !is_dir($this->cacheDirectory)) {
            throw new CacheException(
                title: 'Cache Directory Error',
                message: sprintf("Unable to create cache directory '%s'.", $this->cacheDirectory),
                code: 500
            );
        }
    }

    public function set(string $key, mixed $value, ?int $ttl = null): void
    {
        $data = [
            'expires_at' => time() + ($ttl ?? $this->defaultTtl),
            'content' => $value,
        ];

        if (file_put_contents($this->getFilePath($key), serialize($data), LOCK_EX) === false) {
            throw new CacheException(
                title: 'Cache Write Error',
                message: sprintf("Unable to write cache for key '%s'.", $key),
                code: 500
            );
        }
    }

    public function get(string $key, mixed $default = null): mixed
    {
        $file = $this->getFilePath($key);

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

        $data = unserialize($raw);

        if (time() > $data['expires_at']) {
            $this->delete($key);
            return $default;
        }

        return $data['content'];
    }

    public function delete(string $key): void
    {
        $file = $this->getFilePath($key);

        if (file_exists($file) && !unlink($file)) {
            throw new CacheException(
                title: 'Cache Delete Error',
                message: sprintf("Unable to delete cache for key '%s'.", $key),
                code: 500
            );
        }
    }

    public function clear(): void
    {
        $files = glob($this->cacheDirectory . '/*.cache') ?: [];

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

    private function getFilePath(string $key): string
    {
        return $this->cacheDirectory . '/' . md5($key) . '.cache';
    }
}