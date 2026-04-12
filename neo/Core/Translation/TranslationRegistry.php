<?php

namespace Neo\Core\Translation;

final class TranslationRegistry
{
    private static array $paths = [];

    public static function registerPath(string $path): void
    {
        self::$paths[] = rtrim($path, '/');
    }

    public static function getPaths(): array
    {
        return self::$paths;
    }
}
