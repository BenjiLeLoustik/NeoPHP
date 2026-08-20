<?php

declare(strict_types=1);

namespace Neo\Core\Utils\Scanner;

use Neo\Core\Utils\Scanner\Result\FileScanResult;
use RecursiveCallbackFilterIterator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

class ScannerFileManager
{
    /** @var list<string> */
    private array $paths = [];

    private ?string $pathSegmentFilter = null;

    private ?string $filenameSuffixFilter = null;

    private string $extension = 'php';

    /** @var list<string> */
    private array $excludedSegments = [];

    /**
     * @param list<string> $paths
     */
    public function paths(array $paths): static
    {
        $this->paths = $paths;
        return $this;
    }

    public function withExtension(string $extension): static
    {
        $this->extension = ltrim($extension, '.');
        return $this;
    }

    public function withPathSegment(string $segment): static
    {
        $this->pathSegmentFilter = $segment;
        return $this;
    }

    public function withFilenameSuffix(string $suffix): static
    {
        $this->filenameSuffixFilter = $suffix;
        return $this;
    }

    public function withExcludedSegment(string ...$segments): static
    {
        foreach ($segments as $segment) {
            $this->excludedSegments[] = $segment;
        }
        return $this;
    }

    /**
     * @return list<FileScanResult>
     */
    public function scan(): array
    {
        $results = [];

        foreach ($this->paths as $path) {
            $results = array_merge($results, $this->scanPath($path));
        }

        return $results;
    }

    /**
     * @return list<FileScanResult>
     */
    private function scanPath(string $path): array
    {
        if (!is_dir($path)) {
            return [];
        }

        $realPath = realpath($path);
        if ($realPath === false) {
            return [];
        }

        $results = [];

        $directoryIterator = new RecursiveDirectoryIterator($path);

        $iterator = $this->excludedSegments === []
            ? new RecursiveIteratorIterator($directoryIterator)
            : new RecursiveIteratorIterator(
                new RecursiveCallbackFilterIterator(
                    $directoryIterator,
                    function (\SplFileInfo $current) {
                        if ($current->isDir()) {
                            return !in_array($current->getFilename(), $this->excludedSegments, true);
                        }
                        return true;
                    }
                )
            );

        $candidates = [];

        foreach ($iterator as $file) {
            if (!$file->isFile() || $file->getExtension() !== $this->extension) {
                continue;
            }

            $filePath = $file->getRealPath();
            if ($filePath === false) {
                continue;
            }

            if (!str_starts_with($filePath, $realPath . DIRECTORY_SEPARATOR)) {
                continue;
            }

            if ($this->pathSegmentFilter !== null
                && !str_contains($filePath, DIRECTORY_SEPARATOR . $this->pathSegmentFilter . DIRECTORY_SEPARATOR)
            ) {
                continue;
            }

            if ($this->filenameSuffixFilter !== null
                && !str_ends_with($file->getFilename(), $this->filenameSuffixFilter)
            ) {
                continue;
            }

            $fqcn = $this->resolveFqcn($filePath);
            if ($fqcn === null) {
                continue;
            }

            $candidates[] = [$fqcn, $filePath];
        }

        foreach ($candidates as [$fqcn, $filePath]) {
            if (!class_exists($fqcn, true) && !interface_exists($fqcn, true)) {
                $declaredBefore = get_declared_classes();
                require_once $filePath;

                if (!class_exists($fqcn, false) && !interface_exists($fqcn, false)) {
                    $declaredAfter = get_declared_classes();
                    $new = array_diff($declaredAfter, $declaredBefore);
                    if (empty($new)) {
                        continue;
                    }
                    $fqcn = array_first(array_values($new));
                    if (!class_exists($fqcn, false) && !interface_exists($fqcn, false)) {
                        continue;
                    }
                }
            }

            $results[] = new FileScanResult($fqcn, $filePath);
        }

        return $results;
    }

    private function resolveFqcn(string $filePath): ?string
    {
        $src = file_get_contents($filePath);
        if ($src === false) {
            return null;
        }

        $namespace = '';
        if (preg_match('/namespace\s+([^;]+);/i', $src, $m)) {
            $namespace = trim($m[1]);
        }

        if (!preg_match('/^(?:final\s+|abstract\s+)?class\s+([A-Za-z0-9_]+)/im', $src, $mClass)) {
            return null;
        }

        return $namespace !== '' ? $namespace . '\\' . $mClass[1] : $mClass[1];
    }
}