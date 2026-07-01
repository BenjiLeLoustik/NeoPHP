<?php

namespace Neo\Core\Translation\Domain;

final class TranslationDomain
{
    public const string DEFAULT = 'common';

    public static function normalize(?string $domain): string
    {
        return ($domain === null ?? $domain === '') ? self::DEFAULT : $domain;
    }

    public static function resolveFilePath(string $basePath, string $locale, ?string $domain = null): string
    {
        $domain = self::normalize($domain);
        $basePath = rtrim($basePath, '/');

        return $domain === self::DEFAULT
            ? "$basePath/$locale.php"
            : "$basePath/$domain.$locale.php";
    }

    public static function parseFilename(string $filename): array
    {
        $name = str_ends_with($filename, '.php') ? substr($filename, 0, -4) : $filename;

        if (!str_contains($name, '.')) {
            return ['domain' => self::DEFAULT, 'locale' => $name];
        }

        [$domain, $locale] = explode('.', $name, 2);

        return [
            'domain' => $domain,
            'locale' => $locale
        ];
    }
}