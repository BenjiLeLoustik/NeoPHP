<?php

declare(strict_types=1);

namespace Neo\Core\Translation\Loader;

use Neo\Core\Translation\Domain\TranslationDomain;
use Neo\Core\Translation\Exception\TranslationException;
use Neo\Core\Translation\Registry\TranslationRegistry;

final class TranslationLoader
{
    /** @var array<string, array<string, string>> */
    private array $cache = [];

    /**
     * @return array<string, string>
     * @throws TranslationException
     */
    public function load(string $locale, ?string $domain = null): array
    {
        $domain = TranslationDomain::normalize($domain);
        $cacheKey = "$domain:$locale";

        if (isset($this->cache[$cacheKey])) {
            return $this->cache[$cacheKey];
        }

        $translations = [];

        foreach (TranslationRegistry::getPaths() as $path) {
            $filePath = TranslationDomain::resolveFilePath($path, $locale, $domain);

            if (!file_exists($filePath)) {
                continue;
            }

            $this->bustFileCache($filePath);

            $data = require $filePath;

            if (!is_array($data)) {
                throw new TranslationException(
                    title: 'Translation File Error',
                    message: sprintf("Translation file '%s' must return an array.", $filePath),
                    code: 500
                );
            }

            $translations = array_replace($translations, $data);
        }

        return $this->cache[$cacheKey] = $translations;
    }

    public function invalidate(string $locale, ?string $domain = null): void
    {
        unset($this->cache[TranslationDomain::normalize($domain) . ":$locale"]);
    }

    private function bustFileCache(string $filePath): void
    {
        clearstatcache(true, $filePath);

        if (function_exists('opcache_invalidate')) {
            opcache_invalidate($filePath, true);
        }
    }
}