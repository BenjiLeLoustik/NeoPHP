<?php
declare(strict_types=1);

namespace Neo\Core\Console\Helper;

use FilesystemIterator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

final class Fs
{
    public static function deleteDir(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }

        $it = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST
        );

        foreach ($it as $file) {
            $file->isDir() ? rmdir($file->getRealPath()) : unlink($file->getRealPath());
        }

        rmdir($dir);
    }

    public static function emptyDir(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }

        $it = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST
        );

        foreach ($it as $file) {
            $file->isDir() ? rmdir($file->getRealPath()) : unlink($file->getRealPath());
        }
    }

    public static function ensureDir(string $dir): void
    {
        if (!is_dir($dir)) {
            mkdir($dir, 0777, true);
        }
    }

    public static function pascalCase(string $string): string
    {
        $string = preg_replace('/(?<=[a-z0-9])(?=[A-Z])/', ' ', $string);

        $string = preg_replace('/[^a-zA-Z0-9]+/', ' ', $string);

        $words = array_filter(explode(' ', trim($string)));
        $words = array_map(
            fn($word) => mb_strtoupper(mb_substr($word, 0, 1)) . mb_substr($word, 1),
            $words
        );

        return implode('', $words);
    }

    public static function normalizeDir(string $dir): string
    {
        return trim($dir, '/');
    }
}