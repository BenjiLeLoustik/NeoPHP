<?php
declare(strict_types=1);

namespace Neo\Core\Tools\Helper\File;

use Neo\Core\DI\ContainerRegistry;

class FileHelper
{

    public static function getPublicUrl(string $absolutePath): string
    {
        $publicPath = ContainerRegistry::get()->get('publicPath');

        return $absolutePath
                |> (fn(string $p): string => str_replace(rtrim($publicPath, '/\\'), '', $p))
                |> (fn(string $p): string => str_replace('\\', '/', $p))
                |> (fn(string $p): string => '/' . ltrim($p, '/'));
    }

    public static function getAbsolutePath(string $relativeUrl): string
    {
        $publicPath = ContainerRegistry::get()->get('publicPath');

        return $relativeUrl
                |> (fn(string $u): string => str_replace('\\', '/', $u))
                |> (fn(string $u): string => rtrim($publicPath, '/\\') . '/' . ltrim($u, '/'));
    }

    public static function getExtension(string $path): string
    {
        return strtolower(pathinfo($path, PATHINFO_EXTENSION));
    }

    public static function getFilename(string $path): string
    {
        return pathinfo($path, PATHINFO_FILENAME);
    }

    public static function getSize(string $path): int
    {
        $size = filesize($path);
        return $size !== false ? $size : 0;
    }

    /**
     * @return array{width: int, height: int}
     */
    public static function getDimensions(string $path): array
    {
        $info = getimagesize($path);

        if ($info === false) {
            return [
                'width' => 0,
                'height' => 0,
            ];
        }

        return [
            'width' => $info[0],
            'height' => $info[1]
        ];
    }

    public static function getWidth(string $path): int
    {
        return self::getDimensions($path)['width'];
    }

    public static function getHeight(string $path): int
    {
        return self::getDimensions($path)['height'];
    }

    public static function hashFilename(string $originalFilename): string
    {
        $extension = self::getExtension($originalFilename);
        $hash = bin2hex(random_bytes(16));

        return $extension !== '' ? $hash . '.' . $extension : $hash;
    }

    public static function getMimeType(string $path): ?string
    {
        $mime = mime_content_type($path);
        return $mime !== false ? $mime : null;
    }

    public static function isImage(string $path): bool
    {
        return getimagesize($path) !== false;
    }

    /**
     * @param list<string> $allowed
     */
    public static function hasAllowedExtension(string $path, array $allowed): bool
    {
        return in_array(self::getExtension($path), array_map('strtolower', $allowed), true);
    }

    public static function delete(string $path): bool
    {
        return !file_exists($path) || unlink($path);
    }
}