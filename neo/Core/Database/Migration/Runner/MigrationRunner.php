<?php
declare(strict_types=1);

namespace Neo\Core\Database\Migration\Runner;

use Neo\Core\Database\Access\Connection\DatabaseConnection;
use Neo\Core\Database\DatabaseManager;
use Neo\Core\Database\Migration\MigrationSchemaSnapshot;

final class MigrationRunner
{
    private const string TABLE = 'neo_migrations';

    public function __construct(
        private DatabaseManager $db,
        private string $connection = 'default',
    ) {
        $this->ensureTable();
    }

    private function ensureTable(): void
    {
        $this->db->execute(sprintf("
            CREATE TABLE IF NOT EXISTS `%s` (
                `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
                `connection` VARCHAR(50)  NOT NULL DEFAULT 'default',
                `migration`  VARCHAR(255) NOT NULL,
                `batch`      INT UNSIGNED NOT NULL,
                `applied_at` DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (`id`),
                UNIQUE KEY `uq_migration` (`connection`, `migration`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ", self::TABLE));
    }

    /**
     * @return array<string, array{migration: string, batch: int|string, applied_at: string}>
     */
    public function getApplied(): array
    {
        return array_column(
            $this->db->fetchAll(sprintf(
                'SELECT migration, batch, applied_at FROM `%s` ORDER BY id ASC',
                self::TABLE
            )),
            null,
            'migration'
        );
    }

    public function getLastBatch(): int
    {
        $row = $this->db->fetch(sprintf(
            'SELECT MAX(batch) AS last_batch FROM `%s` WHERE connection = :connection',
            self::TABLE
        ), ['connection' => $this->connection]);

        return (int) ($row['last_batch'] ?? 0);
    }

    /**
     * @return list<string>
     */
    public function getMigrationFiles(string $migrationsPath, bool $recursive = true): array
    {
        $files = glob($migrationsPath . '/MigrationVersion_*.php') ?: [];

        if ($recursive) {
            foreach (glob($migrationsPath . '/*', GLOB_ONLYDIR) ?: [] as $subDir) {
                $files = array_merge(
                    $files, glob($subDir . '/MigrationVersion_*.php') ?: []
                );
            }
        }

        sort($files);
        return $files;
    }

    /**
     * @return list<string>
     */
    public function getPending(string $migrationsPath, bool $recursive = true): array
    {
        $applied = $this->getApplied();
        $files = $this->getMigrationFiles($migrationsPath, $recursive);

        return array_values(array_filter(
            $files, fn(string $f) => !isset($applied[basename($f, '.php')])
        ));
    }

    /**
     * @return list<string>
     */
    public function run(
        string $migrationsPath,
        bool $dryRun = false,
        ?MigrationSchemaSnapshot $snapshot = null
    ): array {
        $ran = [];

        $ran = array_merge($ran, $this->runForPath($migrationsPath, null, $dryRun, $snapshot));

        foreach (glob($migrationsPath . '/*', GLOB_ONLYDIR) ?: [] as $subDir) {
            $connection = basename($subDir);
            $ran = array_merge($ran, $this->runForPath($subDir, $connection, $dryRun, $snapshot));
        }

        return $ran;
    }

    /**
     * @param list<string> $searchPaths
     * @return list<string>
     */
    public function rollback(array $searchPaths, ?MigrationSchemaSnapshot $snapshot = null): array
    {
        $rolledBack = [];

        foreach ($searchPaths as $migrationsPath) {
            foreach (glob($migrationsPath . '/*', GLOB_ONLYDIR) ?: [] as $subDir) {
                $connection = basename($subDir);
                DatabaseConnection::connectTo($connection);
                $db = DatabaseManager::on($connection);
                $runner = new self($db);
                $rolledBack = array_merge($rolledBack, $runner->rollbackForPaths([$subDir], $db));
            }
        }

        $rolledBack = array_merge($rolledBack, $this->rollbackForPaths($searchPaths, $this->db));

        if ($snapshot !== null && count($rolledBack) > 0) {
            $snapshot->take();
        }

        return $rolledBack;
    }

    /**
     * @return list<string>
     */
    private function runForPath(
        string $path,
        ?string $connection,
        bool $dryRun,
        ?MigrationSchemaSnapshot $snapshot
    ): array {
        if ($connection !== null) {
            DatabaseConnection::connectTo($connection);
            $db = DatabaseManager::on($connection);
        } else {
            $db = $this->db;
        }

        $runner = new self($db, $connection ?? 'default');
        $pending = $runner->getPending($path, false);

        if (empty($pending)) {
            return [];
        }

        $batch = $runner->getLastBatch() + 1;
        $ran = [];

        foreach ($pending as $file) {
            $className = basename($file, '.php');

            if (!$dryRun) {
                require_once $file;
                $instance = new $className();
                $instance->up($db);

                $db->execute(
                    sprintf('INSERT INTO `%s` (connection, migration, batch) VALUES (:connection, :migration, :batch)', self::TABLE),
                    ['connection' => $connection ?? $this->connection, 'migration' => $className, 'batch' => $batch]
                );
            }

            $ran[] = $className;
        }

        if (!$dryRun && $snapshot !== null && $connection === null) {
            $snapshot->take();
        }

        return $ran;
    }

    /**
     * @param list<string> $searchPaths
     */
    private function findMigrationFile(string $className, array $searchPaths): ?string
    {
        foreach ($searchPaths as $path) {
            $file = "$path/$className.php";
            if (file_exists($file)) {
                return $file;
            }
        }
        return null;
    }

    /**
     * @param list<string> $searchPaths
     * @return list<string>
     */
    private function rollbackForPaths(array $searchPaths, DatabaseManager $db): array
    {
        $lastBatch = $this->getLastBatch();
        if ($lastBatch === 0) return [];

        $rows = $this->db->fetchAll(
            sprintf('SELECT migration FROM `%s` WHERE connection = :connection AND batch = :batch ORDER BY id DESC', self::TABLE),
            ['connection' => $this->connection, 'batch' => $lastBatch]
        );

        $rolledBack = [];
        foreach ($rows as $row) {
            $className = $row['migration'];
            $file = $this->findMigrationFile($className, $searchPaths);

            if ($file !== null) {
                require_once $file;
                $instance = new $className();
                $instance->down($db);
            }

            $this->db->execute(
                sprintf('DELETE FROM `%s` WHERE connection = :connection AND migration = :migration', self::TABLE),
                ['connection' => $this->connection, 'migration' => $className]
            );

            $rolledBack[] = $className;
        }

        return $rolledBack;
    }
}