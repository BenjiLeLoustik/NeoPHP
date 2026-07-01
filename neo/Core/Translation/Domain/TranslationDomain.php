<?php
declare(strict_types=1);

namespace Neo\Core\Translation\Domain;

final class TranslationDomain
{
    public const string DEFAULT = 'common';

    public static function normalize(?string $domain): string
    {
        if ($domain === null || $domain === '') {
            return self::DEFAULT;
        }

        return $domain;
    }

    public static function resolveFilePath(string $basePath, string $locale, ?string $domain = null): string
    {
        $domain = self::normalize($domain);
        $basePath = rtrim($basePath, '/');

        return "$basePath/$locale/$domain.php";
    }
}