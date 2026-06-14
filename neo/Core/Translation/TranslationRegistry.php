<?php

namespace Neo\Core\Translation;

final class TranslationRegistry
{
    /** @var list<string> */
    private static array $paths = [];

    public static function registerPath(string $path): void
    {
        self::$paths[] = rtrim($path, '/');
    }

    /**
     * @return list<string>
     */
    public static function getPaths(): array
    {
        return self::$paths;
    }
}
