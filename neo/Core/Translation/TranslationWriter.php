<?php

namespace Neo\Core\Translation;

final class TranslationWriter
{
    private TranslationLoader $loader;

    public function __construct(TranslationLoader $loader)
    {
        $this->loader = $loader;
    }

    public function ensure(
        string $locale,
        string $file,
        array  $segments,
        string $defaultValue
    ): void {
        foreach (TranslationRegistry::getPaths() as $path) {
            $filePath = "$path/$locale/$file.php";

            if (!file_exists($filePath)) {
                $this->createFile($filePath);
            }

            $translations = require $filePath;

            if (!is_array($translations)) {
                throw new \Neo\Core\Translation\Exception\TranslationException(
                    title: 'Translation File Error',
                    message: "Le fichier de traduction '{$filePath}' doit retourner un tableau.",
                    code: 500
                );
            }

            if ($this->hasKey($translations, $segments)) {
                return;
            }

            $this->writeKey($filePath, $translations, $segments, $defaultValue);
            $this->loader->invalidate($locale, $file);
            return;
        }
    }

    private function createFile(string $filePath): void
    {
        $dir = dirname($filePath);

        if (!is_dir($dir) && !mkdir($dir, 0775, true) && !is_dir($dir)) {
            throw new \Neo\Core\Translation\Exception\TranslationException(
                title: 'Translation Directory Error',
                message: "Impossible de créer le répertoire de traduction '{$dir}'.",
                code: 500
            );
        }

        if (file_put_contents($filePath, "<?php\n\nreturn [\n];\n") === false) {
            throw new \Neo\Core\Translation\Exception\TranslationException(
                title: 'Translation File Error',
                message: "Impossible de créer le fichier de traduction '{$filePath}'.",
                code: 500
            );
        }
    }

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

    private function writeKey(
        string $filePath,
        array  $translations,
        array  $segments,
        string $value
    ): void {
        $ref  = &$translations;
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

    private function dumpPhpFile(string $filePath, array $translations): void
    {
        $content = "<?php\n\nreturn " . $this->arrayToPhp($translations) . ";\n";

        if (file_put_contents($filePath, $content) === false) {
            throw new \Neo\Core\Translation\Exception\TranslationException(
                title: 'Translation Write Error',
                message: "Impossible d'écrire dans le fichier de traduction '{$filePath}'.",
                code: 500
            );
        }
    }

    private function arrayToPhp(array $array, int $level = 0): string
    {
        $indent = str_repeat('    ', $level);
        $lines  = [];

        foreach ($array as $key => $value) {
            $keyExport = is_int($key) ? $key : "'" . str_replace("'", "\\'", $key) . "'";
            if (is_array($value)) {
                $lines[] = "$keyExport => " . $this->arrayToPhp($value, $level + 1);
            } else {
                $val     = str_replace("'", "\\'", $value);
                $lines[] = "$keyExport => '$val'";
            }
        }

        $inner = implode(",\n" . str_repeat('    ', $level + 1), $lines);
        return "[\n" . str_repeat('    ', $level + 1) . $inner . "\n" . $indent . "]";
    }
}