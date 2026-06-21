<?php

namespace Neo\Core\Translation\Loader;

use Neo\Core\Translation\Exception\TranslationException;
use Neo\Core\Translation\TranslationRegistry;

final class TranslationLoader
{
    /** @var array<string, array<string, mixed>> */
    private array $cache = [];

    /**
     * @return array<string, mixed>
     * @throws TranslationException
     */
    public function load(string $locale, string $file): array
    {
        $cacheKey = "$locale.$file";

        if (isset($this->cache[$cacheKey])) {
            return $this->cache[$cacheKey];
        }

        $translations = [];

        foreach (TranslationRegistry::getPaths() as $path) {
            $filePath = "$path/$locale/$file.php";

            if (!file_exists($filePath)) {
                continue;
            }

            $data = require $filePath;

            if (!is_array($data)) {
                throw new TranslationException(
                    title: 'Translation File Error',
                    message: sprintf("Translation file '%s' must return an array.", $filePath),
                    code: 500
                );
            }

            $translations = array_replace_recursive($translations, $data);
        }

        return $this->cache[$cacheKey] = $translations;
    }

    public function invalidate(string $locale, string $file): void
    {
        unset($this->cache["$locale.$file"]);
    }
}