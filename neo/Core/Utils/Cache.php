<?php
declare(strict_types=1);

namespace Neo\Core\Utils;

use Neo\Core\DI\Container;
use Neo\Core\Error\Exception\FrameworkException;

class Cache
{
    private string $cacheDirectory;
    private int $defaultTtl;

    public function __construct(Container $container)
    {
        $config               = $container->get(Config::class)->from('cache')->all();
        $this->cacheDirectory = $container->get('storagePath') . '/cache';
        $this->defaultTtl     = (int) ($config['ttl'] ?? 3600);

        if (!is_dir($this->cacheDirectory) && !mkdir($this->cacheDirectory, 0777, true) && !is_dir($this->cacheDirectory)) {
            throw new FrameworkException(
                title: 'Cache Directory Error',
                message: "Impossible de créer le répertoire de cache '{$this->cacheDirectory}'.",
                code: 500
            );
        }
    }

    public function set(string $key, mixed $value, ?int $ttl = null): void
    {
        $data = [
            'expires_at' => time() + ($ttl ?? $this->defaultTtl),
            'content'    => $value,
        ];

        if (file_put_contents($this->getFilePath($key), serialize($data), LOCK_EX) === false) {
            throw new FrameworkException(
                title: 'Cache Write Error',
                message: "Impossible d'écrire le cache pour la clé '{$key}'.",
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
            throw new FrameworkException(
                title: 'Cache Read Error',
                message: "Impossible de lire le cache pour la clé '{$key}'.",
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
            throw new FrameworkException(
                title: 'Cache Delete Error',
                message: "Impossible de supprimer le cache pour la clé '{$key}'.",
                code: 500
            );
        }
    }

    public function clear(): void
    {
        $files = glob($this->cacheDirectory . '/*.cache') ?: [];

        foreach ($files as $file) {
            if (is_file($file) && !unlink($file)) {
                throw new FrameworkException(
                    title: 'Cache Clear Error',
                    message: "Impossible de supprimer le fichier de cache '{$file}'.",
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