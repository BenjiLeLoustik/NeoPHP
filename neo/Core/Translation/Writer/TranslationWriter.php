<?php

namespace Neo\Core\Translation\Writer;

use Neo\Core\Translation\Exception\TranslationException;
use Neo\Core\Translation\Loader\TranslationLoader;
use Neo\Core\Translation\TranslationRegistry;

final class TranslationWriter
{
    private TranslationLoader $loader;

    public function __construct(TranslationLoader $loader)
    {
        $this->loader = $loader;
    }

    /**
     * @param list<string> $segments
     * @throws TranslationException
     */
    public function ensure(
        string $locale,
        string $file,
        array $segments,
        string $defaultValue
    ): void {
        $path = TranslationRegistry::getPaths()[0] ?? null;

        if ($path === null) {
            return;
        }

        $filePath = "$path/$locale/$file.php";

        if (!file_exists($filePath)) {
            $this->createFile($filePath);
        }

        $translations = require $filePath;

        if (!is_array($translations)) {
            throw new TranslationException(
                title: 'Translation File Error',
                message: sprintf("Translation file '%s' must return an array.", $filePath),
                code: 500
            );
        }

        if ($this->hasKey($translations, $segments)) {
            return;
        }

        $this->writeKey($filePath, $translations, $segments, $defaultValue);
        $this->loader->invalidate($locale, $file);
    }

    /**
     * @throws TranslationException
     */
    private function createFile(string $filePath): void
    {
        $dir = dirname($filePath);

        if (!is_dir($dir) && !mkdir($dir, 0775, true) && !is_dir($dir)) {
            throw new TranslationException(
                title: 'Translation Directory Error',
                message: sprintf("Unable to create translation directory '%s'.", $dir),
                code: 500
            );
        }

        if (file_put_contents($filePath, "<?php\n\nreturn [\n];\n") === false) {
            throw new TranslationException(
                title: 'Translation File Error',
                message: sprintf("Unable to create translation file '%s'.", $filePath),
                code: 500
            );
        }
    }

    /**
     * @param array<string, mixed> $array
     * @param list<string> $segments
     */
    private function hasKey(array $array, array $segments): bool
    {
        foreach ($segments as $segment) {
            if (!isset($array[$segment])) {
                return false;
            }
            $array = $array[$segment];
        }

        return true;
    }

    /**
     * @param list<string> $segments
     * @param array<string, mixed> $translations
     * @throws TranslationException
     */
    private function writeKey(
        string $filePath,
        array $translations,
        array $segments,
        string $value
    ): void {
        $ref = &$translations;
        $last = array_pop($segments);

        foreach ($segments as $segment) {
            if (!isset($ref[$segment]) || !is_array($ref[$segment])) {
                $ref[$segment] = [];
            }
            $ref = &$ref[$segment];
        }

        $ref[$last] = $value;

        $this->dumpPhpFile($filePath, $translations);
    }

    /**
     * @param array<string, mixed> $translations
     * @throws TranslationException
     */
    private function dumpPhpFile(string $filePath, array $translations): void
    {
        $content = "<?php\n\nreturn " . $this->arrayToPhp($translations) . ";\n";

        if (file_put_contents($filePath, $content, LOCK_EX) === false) {
            throw new TranslationException(
                title: 'Translation Write Error',
                message: sprintf("Unable to write to translation file '%s'.", $filePath),
                code: 500
            );
        }
    }

    /**
     * @param array<string|int, mixed> $array
     * @param int $level
     * @return string
     */
    private function arrayToPhp(array $array, int $level = 0): string
    {
        $indent = str_repeat('    ', $level);
        $lines = [];

        foreach ($array as $key => $value) {
            $keyExport = is_int($key) ? $key : "'" . str_replace("'", "\\'", $key) . "'";
            if (is_array($value)) {
                $lines[] = "$keyExport => " . $this->arrayToPhp($value, $level + 1);
            } elseif (is_string($value)) {
                $val = str_replace("'", "\\'", $value);
                $lines[] = "$keyExport => '$val'";
            } else {
                $lines[] = "$keyExport => " . var_export($value, true);
            }
        }

        $inner = implode(",\n" . str_repeat('    ', $level + 1), $lines);
        return "[\n" . str_repeat('    ', $level + 1) . $inner . "\n" . $indent . "]";
    }
}