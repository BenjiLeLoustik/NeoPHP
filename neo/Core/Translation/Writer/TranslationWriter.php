<?php

namespace Neo\Core\Translation\Writer;

use Neo\Core\Translation\Exception\TranslationException;
use Neo\Core\Translation\Loader\TranslationLoader;
use Neo\Core\Translation\TranslationRegistry;

final class TranslationWriter
{
    public function __construct(private TranslationLoader $loader) {}

    /**
     * @throws TranslationException
     */
    public function ensure(string $locale, string $key): void
    {
        $path = TranslationRegistry::getPaths()[0] ?? null;

        if ($path === null) {
            return;
        }

        $filePath = "$path/$locale.php";

        if (!file_exists($filePath)) {
            $this->createFile($filePath);
        }

        $translations = (static fn() => require $filePath)();

        if (!is_array($translations)) {
            throw new TranslationException(
                title: 'Translation File Error',
                message: sprintf("Translation file '%s' must return an array.", $filePath),
                code: 500
            );
        }

        if (array_key_exists($key, $translations)) {
            return;
        }

        $translations[$key] = $key;

        $this->dumpPhpFile($filePath, $translations);

        if (function_exists('opcache_invalidate')) {
            opcache_invalidate($filePath, true);
        }

        $this->loader->invalidate($locale);
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

        if (file_put_contents($filePath, "<?php\n\ndeclare(strict_types=1);\n\nreturn [\n];\n") === false) {
            throw new TranslationException(
                title: 'Translation File Error',
                message: sprintf("Unable to create translation file '%s'.", $filePath),
                code: 500
            );
        }
    }

    /**
     * @param array<string, string> $translations
     * @throws TranslationException
     */
    private function dumpPhpFile(string $filePath, array $translations): void
    {
        $lines = [];
        foreach ($translations as $key => $value) {
            $lines[] = '    ' . var_export($key, true) . ' => ' . var_export($value, true);
        }

        $content = "<?php\n\ndeclare(strict_types=1);\n\nreturn [\n" . implode(",\n", $lines) . "\n];\n";

        if (file_put_contents($filePath, $content, LOCK_EX) === false) {
            throw new TranslationException(
                title: 'Translation Write Error',
                message: sprintf("Unable to write to translation file '%s'.", $filePath),
                code: 500
            );
        }
    }
}