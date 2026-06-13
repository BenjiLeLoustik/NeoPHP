<?php
declare(strict_types=1);

namespace Neo\Core\Extension\Path;

class PathExtension
{
    public function join(string ...$parts): string
    {
        $parts = array_filter($parts, fn($p) => $p !== '');
        $path  = implode(DIRECTORY_SEPARATOR, $parts);
        return $this->normalize($path);
    }

    public function normalize(string $path): string
    {
        $path  = str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $path);
        $parts = explode(DIRECTORY_SEPARATOR, $path);
        $stack = [];

        foreach ($parts as $part) {
            if ($part === '..') {
                array_pop($stack);
            } elseif ($part !== '.' && $part !== '') {
                $stack[] = $part;
            }
        }

        $normalized = implode(DIRECTORY_SEPARATOR, $stack);

        if (str_starts_with($path, DIRECTORY_SEPARATOR)) {
            $normalized = DIRECTORY_SEPARATOR . $normalized;
        }

        return $normalized;
    }

    public function relative(string $from, string $to): string
    {
        $from = explode(DIRECTORY_SEPARATOR, $this->normalize($from));
        $to = explode(DIRECTORY_SEPARATOR, $this->normalize($to));
        $common = 0;

        foreach ($from as $i => $part) {
            if (isset($to[$i]) && $to[$i] === $part) {
                $common++;
            } else {
                break;
            }
        }

        $up = array_fill(0, count($from) - $common, '..');
        $down = array_slice($to, $common);

        return implode(DIRECTORY_SEPARATOR, array_merge($up, $down)) ?: '.';
    }

    public function isAbsolute(string $path): bool
    {
        return str_starts_with($path, '/') || preg_match('/^[A-Za-z]:[\\\\\/]/', $path) === 1;
    }

    public function extension(string $path): string
    {
        return pathinfo($path, PATHINFO_EXTENSION);
    }

    public function filename(string $path): string
    {
        return pathinfo($path, PATHINFO_FILENAME);
    }

    public function basename(string $path): string
    {
        return pathinfo($path, PATHINFO_BASENAME);
    }

    public function dirname(string $path): string
    {
        return pathinfo($path, PATHINFO_DIRNAME);
    }

    public function withoutExtension(string $path): string
    {
        $dir = $this->dirname($path);
        $name = $this->filename($path);
        return $dir !== '.' ? $dir . DIRECTORY_SEPARATOR . $name : $name;
    }

    public function changeExtension(string $path, string $ext): string
    {
        return $this->withoutExtension($path) . '.' . ltrim($ext, '.');
    }
}