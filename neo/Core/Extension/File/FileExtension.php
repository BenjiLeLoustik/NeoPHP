<?php
declare(strict_types=1);

namespace Neo\Core\Extension\File;

class FileExtension
{
    public function extension(string $path): string
    {
        return strtolower(pathinfo($path, PATHINFO_EXTENSION));
    }

    public function filename(string $path, bool $withExtension = true): string
    {
        return $withExtension
            ? pathinfo($path, PATHINFO_BASENAME)
            : pathinfo($path, PATHINFO_FILENAME);
    }

    public function directory(string $path): string
    {
        return pathinfo($path, PATHINFO_DIRNAME);
    }

    public function size(string $path): int
    {
        return file_exists($path) ? (int) filesize($path) : 0;
    }

    public function humanSize(string $path, int $decimals = 2): string
    {
        $bytes = $this->size($path);
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $i = 0;

        while ($bytes >= 1024 && $i < count($units) - 1) {
            $bytes /= 1024;
            $i++;
        }

        return round($bytes, $decimals) . ' ' . $units[$i];
    }

    public function mimeType(string $path): string|false
    {
        if (function_exists('mime_content_type')) {
            return mime_content_type($path);
        }

        if (class_exists(\finfo::class)) {
            $finfo = new \finfo(FILEINFO_MIME_TYPE);
            return $finfo->file($path);
        }

        $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        $map = [
            'jpg' => 'image/jpeg',
            'jpeg' => 'image/jpeg',
            'png' => 'image/png',
            'gif' => 'image/gif',
            'webp' => 'image/webp',
            'svg' => 'image/svg+xml',
            'pdf' => 'application/pdf',
            'json' => 'application/json',
            'xml' => 'application/xml',
            'zip' => 'application/zip',
            'html' => 'text/html',
            'css' => 'text/css',
            'js' => 'text/javascript',
            'txt' => 'text/plain',
            'csv' => 'text/csv',
            'mp4' => 'video/mp4',
            'mp3' => 'audio/mpeg',
        ];

        return $map[$ext] ?? false;
    }

    public function isImage(string $path): bool
    {
        return in_array($this->extension($path), ['jpg', 'jpeg', 'png', 'gif', 'webp', 'svg', 'bmp', 'ico']);
    }

    public function lastModified(string $path): int|false
    {
        return file_exists($path) ? filemtime($path) : false;
    }

    public function read(string $path): string|false
    {
        return file_exists($path) ? file_get_contents($path) : false;
    }

    public function write(string $path, string $content, bool $append = false): bool
    {
        $flags = $append ? FILE_APPEND : 0;
        return file_put_contents($path, $content, $flags) !== false;
    }

    public function readLines(string $path): array
    {
        if (!file_exists($path)) return [];
        return file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [];
    }

    public function readJson(string $path): array|null
    {
        $content = $this->read($path);
        if ($content === false) return null;

        $decoded = json_decode($content, true);
        return is_array($decoded) ? $decoded : null;
    }

    public function writeJson(string $path, array $data, bool $pretty = true): bool
    {
        $flags = $pretty ? JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE : JSON_UNESCAPED_UNICODE;
        return $this->write($path, json_encode($data, $flags));
    }

    public function copy(string $from, string $to): bool
    {
        $this->ensureDirectory(dirname($to));
        return copy($from, $to);
    }

    public function move(string $from, string $to): bool
    {
        $this->ensureDirectory(dirname($to));
        return rename($from, $to);
    }

    public function delete(string $path): bool
    {
        return file_exists($path) && unlink($path);
    }

    public function exists(string $path): bool
    {
        return file_exists($path);
    }

    public function ensureDirectory(string $path, int $permissions = 0755): bool
    {
        if (is_dir($path)) return true;
        return mkdir($path, $permissions, true);
    }

    public function deleteDirectory(string $path): bool
    {
        if (!is_dir($path)) return false;

        foreach (scandir($path) as $item) {
            if ($item === '.' || $item === '..') continue;
            $full = $path . DIRECTORY_SEPARATOR . $item;
            is_dir($full) ? $this->deleteDirectory($full) : unlink($full);
        }

        return rmdir($path);
    }

    public function listFiles(string $directory, ?string $extension = null): array
    {
        if (!is_dir($directory)) return [];

        $files = array_filter(scandir($directory), fn($f) => is_file($directory . DIRECTORY_SEPARATOR . $f));

        if ($extension !== null) {
            $files = array_filter($files, fn($f) => $this->extension($f) === strtolower($extension));
        }

        return array_values($files);
    }

    public function sanitizeFilename(string $filename): string
    {
        $filename = preg_replace('/[^\w\-.]/', '_', $filename);
        return preg_replace('/_+/', '_', trim($filename, '_'));
    }
}