<?php

namespace Neo\Core\Tools\Scanner;

use Neo\Core\Tools\Scanner\Interface\ScannerInterface;

class AbstractScanner implements ScannerInterface
{
    protected array $directories = [];
    protected ?string $fileSuffix = null;

    public function in(string $directory): static
    {
        $this->directories[] = [
            'path' => $directory,
            'subfolder' => null
        ];
        return $this;
    }

    public function inSubfolder(string $directory, string $subfolder): static
    {
        $this->directories[] = [
            'path' => $directory,
            'subfolder' => $subfolder
        ];
        return $this;
    }

    public function withSuffix(string $suffix): static
    {
        $this->fileSuffix = $suffix;
        return $this;
    }

    protected function loadClasses(string $directory, ?string $subfolder = null): array
    {
        if (!is_dir($directory)) {
            return [];
        }

        $classes = [];
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($directory, \FilesystemIterator::SKIP_DOTS)
        );

        foreach ($iterator as $file) {
            if ($file->getExtension() !== 'php') {
                continue;
            }

            if (
                $subfolder !== null &&
                !str_contains($file->getPathname(), DIRECTORY_SEPARATOR . $subfolder . DIRECTORY_SEPARATOR)
            ) {
                continue;
            }

            if (
                $this->fileSuffix !== null &&
                !str_ends_with($file->getFilename(), $this->fileSuffix)
            ) {
                continue;
            }

            $path = $file->getPathname();
            $content = file_get_contents($path);

            if ($content === false) {
                continue;
            }

            $namespace = '';
            if (preg_match('/namespace\s+(.+);/i', $content, $match)) {
                $namespace = trim($match[1]) . '\\';
            }

            if (preg_match('/class\s+([A-Za-z0-9_]+)/i', $content, $match)) {
                $fqcn = $namespace . $match[1];
                require_once $path;
                $classes[] = $fqcn;
            }
        }

        return $classes;
    }

    public function getResults(): array
    {
        return [];
    }
}