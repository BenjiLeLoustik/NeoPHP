<?php

namespace Neo\Core\Translation;

final class TranslationLoader
{
    private array $cache = [];

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
                throw new \Neo\Core\Translation\Exception\TranslationException(
                    title: 'Translation File Error',
                    message: "Le fichier de traduction '{$filePath}' doit retourner un tableau.",
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