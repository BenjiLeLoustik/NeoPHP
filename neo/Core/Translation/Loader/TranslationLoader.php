<?php

namespace Neo\Core\Translation\Loader;

use Neo\Core\Translation\Exception\TranslationException;
use Neo\Core\Translation\TranslationRegistry;

final class TranslationLoader
{
    /** @var array<string, array<string, string>> */
    private array $cache = [];

    /**
     * @return array<string, string>
     * @throws TranslationException
     */
    public function load(string $locale): array
    {
        if (isset($this->cache[$locale])) {
            return $this->cache[$locale];
        }

        $translations = [];

        foreach (TranslationRegistry::getPaths() as $path) {
            $filePath = "$path/$locale.php";

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

            $translations = array_replace($translations, $data);
        }

        return $this->cache[$locale] = $translations;
    }

    public function invalidate(string $locale): void
    {
        unset($this->cache[$locale]);
    }
}