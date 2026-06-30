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
        $string = self::stripAccents($string);
        $string = str_replace('-', '_', $string);
        $string = preg_replace('/[^a-zA-Z0-9_\s]+/', ' ', $string);

        $tokens = array_filter(explode(' ', trim($string)), fn($t) => $t !== '');

        $result = '';
        foreach ($tokens as $token) {
            $parts = explode('_', $token);
            $parts = array_map(
                fn($part) => $part === ''
                    ? ''
                    : mb_strtoupper(mb_substr($part, 0, 1)) . mb_substr($part, 1),
                $parts
            );
            $result .= implode('_', $parts);
        }

        return $result;
    }

    private static function stripAccents(string $string): string
    {
        static $map = [
            'à' => 'a', 'â' => 'a', 'ä' => 'a', 'á' => 'a', 'ã' => 'a', 'å' => 'a',
            'À' => 'A', 'Â' => 'A', 'Ä' => 'A', 'Á' => 'A', 'Ã' => 'A', 'Å' => 'A',
            'ç' => 'c', 'Ç' => 'C',
            'è' => 'e', 'é' => 'e', 'ê' => 'e', 'ë' => 'e',
            'È' => 'E', 'É' => 'E', 'Ê' => 'E', 'Ë' => 'E',
            'ì' => 'i', 'î' => 'i', 'ï' => 'i', 'í' => 'i',
            'Ì' => 'I', 'Î' => 'I', 'Ï' => 'I', 'Í' => 'I',
            'ñ' => 'n', 'Ñ' => 'N',
            'ò' => 'o', 'ô' => 'o', 'ö' => 'o', 'ó' => 'o', 'õ' => 'o',
            'Ò' => 'O', 'Ô' => 'O', 'Ö' => 'O', 'Ó' => 'O', 'Õ' => 'O',
            'ù' => 'u', 'û' => 'u', 'ü' => 'u', 'ú' => 'u',
            'Ù' => 'U', 'Û' => 'U', 'Ü' => 'U', 'Ú' => 'U',
            'ý' => 'y', 'ÿ' => 'y', 'Ý' => 'Y',
            'œ' => 'oe', 'Œ' => 'Oe',
            'æ' => 'ae', 'Æ' => 'Ae',
        ];

        return strtr($string, $map);
    }

    public static function normalizeDir(string $dir): string
    {
        return trim($dir, '/');
    }
}