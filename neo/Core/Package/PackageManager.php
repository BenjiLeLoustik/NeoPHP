<?php

declare(strict_types=1);

namespace Neo\Core\Package;

use Neo\Core\Package\Exception\PackageException;
use Neo\Core\Package\Interface\PackageInterface;

final class PackageManager
{
    private const string LOCK_FILENAME = 'packages.lock.json';

    /**
     * @var array<string, string>
     */
    private const array LINKED_FOLDERS = [
        'Controllers' => 'App/Controllers/_packages',
        'Views' => 'Templates/_packages',
        'Listeners' => 'App/Event/Listener/_packages',
        'Crons' => 'App/Crons/_packages',
        'Commands' => 'App/Commands/_packages',
    ];

    public static function sync(mixed $event = null): void
    {
        $manager = new self();
        $manager->syncAllProjects();
    }

    /**
     * @return array{synced: int<0, max>, projects: list<string>}
     */
    public function syncAllProjects(): array
    {
        $basePath = $this->resolveBasePath();
        $srcDir = $basePath . '/src';

        if (!is_dir($srcDir)) {
            return [
                'synced' => 0,
                'projects' => []
            ];
        }

        $projects = array_filter(
            glob($srcDir . '/*', GLOB_ONLYDIR) ?: [],
            static fn(string $dir): bool => file_exists($dir . '/Config/app.config.php')
        );

        $syncedProjects = [];

        foreach ($projects as $projectDir) {
            $this->syncProject($projectDir);
            $syncedProjects[] = basename($projectDir);
        }

        return [
            'synced' => count($syncedProjects),
            'projects' => $syncedProjects
        ];
    }

    /**
     * @throws PackageException
     */
    public function syncProject(string $appPath): void
    {
        $configFile = $appPath . '/Config/app.config.php';

        if (!file_exists($configFile)) {
            return;
        }

        /** @var array<string, mixed> $config */
        $config = require $configFile;

        /** @var array<int, class-string<PackageInterface>> $packageClasses */
        $packageClasses = $config['packages'] ?? [];

        $lockPath = $appPath . '/Storage/' . self::LOCK_FILENAME;
        $previousLinks = $this->readLock($lockPath);
        $currentLinks = [];

        foreach ($packageClasses as $packageClass) {
            if (!class_exists($packageClass)) {
                throw new PackageException(
                    title: 'Package Not Found',
                    message: sprintf("Package class '%s' does not exist.", $packageClass),
                );
            }

            $package = new $packageClass();

            if (!$package instanceof PackageInterface) {
                throw new PackageException(
                    title: 'Invalid Package',
                    message: sprintf("Class '%s' must implement PackageInterface.", $packageClass),
                );
            }

            $currentLinks = array_merge(
                $currentLinks,
                $this->linkPackage($package, $appPath)
            );
        }

        $this->removeOrphans($previousLinks, $currentLinks);
        $this->writeLock($lockPath, $currentLinks);
    }

    /**
     * @return list<string>
     */
    private function linkPackage(PackageInterface $package, string $appPath): array
    {
        $name = $package->getName();
        $packagePath = rtrim($package->getPath(), '/\\');
        $created = [];

        foreach (self::LINKED_FOLDERS as $sourceFolder => $targetPrefix) {
            $source = $packagePath . '/' . $sourceFolder;

            if (!is_dir($source)) {
                continue;
            }

            $target = $appPath . '/' . $targetPrefix . '/' . $name;
            $this->linkDirectory($source, $target);
            $created[] = $target;
        }

        $migrationsSource = $packagePath . '/Migrations';
        if (is_dir($migrationsSource)) {
            $created = array_merge(
                $created,
                $this->linkMigrationFiles($migrationsSource, $appPath . '/Database/Migrations', $name)
            );
        }

        $configSource = $packagePath . '/Config';
        if (is_dir($configSource)) {
            $this->copyConfigDefaults($configSource, $appPath . '/Config');
        }

        $moduleSource = $packagePath . '/Module.php';
        if (file_exists($moduleSource)) {
            $target = $appPath . '/App/_packages/' . $name . 'Module.php';
            $this->linkFile($moduleSource, $target);
            $created[] = $target;
        }

        return $created;
    }

    private function linkDirectory(string $source, string $target): void
    {
        $this->ensureParentDirectory($target);

        if (is_link($target) || is_dir($target) || file_exists($target)) {
            $this->removePath($target);
        }

        if (!@symlink($source, $target)) {
            $this->copyDirectoryRecursive($source, $target);
        }
    }

    private function linkFile(string $source, string $target): void
    {
        $this->ensureParentDirectory($target);

        if (is_link($target) || file_exists($target)) {
            $this->removePath($target);
        }

        if (!@symlink($source, $target)) {
            copy($source, $target);
        }
    }

    /**
     * @return list<string>
     */
    private function linkMigrationFiles(string $source, string $targetDir, string $packageName): array
    {
        $created = [];
        $files = glob($source . '/*.php') ?: [];
        $expectedPrefix = 'MigrationVersion_' . $packageName . '_';

        if (!is_dir($targetDir)) {
            mkdir($targetDir, 0755, true);
        }

        foreach ($files as $file) {
            $filename = basename($file);

            if (!str_starts_with($filename, $expectedPrefix)) {
                throw new PackageException(
                    title: 'Invalid Migration Name',
                    message: sprintf(
                        "Migration '%s' from package '%s' must be prefixed with '%s' to avoid collisions with other migrations.",
                        $filename,
                        $packageName,
                        $expectedPrefix
                    ),
                );
            }

            $target = $targetDir . '/' . $filename;
            $this->linkFile($file, $target);
            $created[] = $target;
        }

        return $created;
    }

    private function copyConfigDefaults(string $source, string $targetDir): void
    {
        if (!is_dir($targetDir)) {
            mkdir($targetDir, 0755, true);
        }

        foreach (glob($source . '/*.config.php') ?: [] as $file) {
            $target = $targetDir . '/' . basename($file);

            if (!file_exists($target)) {
                copy($file, $target);
            }
        }
    }

    /**
     * @param list<string> $previous
     * @param list<string> $current
     */
    private function removeOrphans(array $previous, array $current): void
    {
        foreach (array_diff($previous, $current) as $orphan) {
            $this->removePath($orphan);
        }
    }

    private function removePath(string $path): void
    {
        if (is_link($path)) {
            unlink($path);
            return;
        }

        if (is_dir($path)) {
            $this->removeDirectoryRecursive($path);
            return;
        }

        if (file_exists($path)) {
            unlink($path);
        }
    }

    private function copyDirectoryRecursive(string $source, string $target): void
    {
        if (!is_dir($target)) {
            mkdir($target, 0755, true);
        }

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($source, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::SELF_FIRST
        );

        foreach ($iterator as $item) {
            $relative = substr($item->getPathname(), strlen($source) + 1);
            $destination = $target . '/' . $relative;

            if ($item->isDir()) {
                if (!is_dir($destination)) {
                    mkdir($destination, 0755, true);
                }
            } else {
                copy($item->getPathname(), $destination);
            }
        }
    }

    private function removeDirectoryRecursive(string $path): void
    {
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($path, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST
        );

        foreach ($iterator as $item) {
            $item->isDir() ? rmdir($item->getPathname()) : unlink($item->getPathname());
        }

        rmdir($path);
    }

    private function ensureParentDirectory(string $path): void
    {
        $parent = dirname($path);

        if (!is_dir($parent)) {
            mkdir($parent, 0755, true);
        }
    }

    /**
     * @return list<string>
     */
    private function readLock(string $lockPath): array
    {
        if (!file_exists($lockPath)) {
            return [];
        }

        $decoded = json_decode((string) file_get_contents($lockPath), true);

        return is_array($decoded) ? array_values(array_map('strval', $decoded)) : [];
    }

    /**
     * @param list<string> $links
     */
    private function writeLock(string $lockPath, array $links): void
    {
        $this->ensureParentDirectory($lockPath);
        file_put_contents($lockPath, json_encode(array_values($links), JSON_PRETTY_PRINT));
    }

    private function resolveBasePath(): string
    {
        $resolved = realpath(__DIR__ . '/../../../');

        if ($resolved === false) {
            throw new PackageException(
                title: 'Invalid Base Path',
                message: 'Unable to resolve the framework root path.',
            );
        }

        return $resolved;
    }
}