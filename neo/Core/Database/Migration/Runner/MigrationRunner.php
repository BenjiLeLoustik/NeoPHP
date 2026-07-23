<?php
declare(strict_types=1);

namespace Neo\Core\Database\Migration\Runner;

use Neo\Core\Database\Access\Connection\DatabaseConnection;
use Neo\Core\Database\DatabaseManager;
use Neo\Core\Database\Exception\DatabaseException;
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
                UNIQUE KEY `uq_migration` (`migration`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ", self::TABLE));
    }

    /**
     * @return array<string, array{migration: string, batch: int, applied_at: string}>
     * @throws DatabaseException
     */
    public function getApplied(): array
    {
        return array_column(
            $this->db->fetchAll(sprintf(
                'SELECT migration, batch, applied_at FROM `%s` WHERE connection = :connection ORDER BY id ASC',
                self::TABLE
            ), ['connection' => $this->connection]),
            null,
            'migration'
        );
    }

    /**
     * @throws DatabaseException
     */
    public function getLastBatch(): int
    {
        $row = $this->db->fetch(sprintf(
            'SELECT MAX(batch) AS last_batch FROM `%s` WHERE connection = :connection',
            self::TABLE
        ), ['connection' => $this->connection]);

        return (int) ($row['last_batch'] ?? 0);
    }

    /**
     * @param string $migrationsPath
     * @return array<int, string>
     */
    public function getMigrationFiles(string $migrationsPath): array
    {
        $files = [];

        $files = array_merge(
            $files, glob($migrationsPath . '/MigrationVersion_*.php') ?: []
        );

        foreach (glob($migrationsPath . '/*', GLOB_ONLYDIR) ?: [] as $subDir) {
            $files = array_merge(
                $files, glob($subDir . '/MigrationVersion_*.php') ?: []
            );
        }

        sort($files);
        return $files;
    }

    /**
     * @param string $migrationsPath
     * @return array<int, string>
     * @throws DatabaseException
     */
    public function getPending(string $migrationsPath): array
    {
        $applied = $this->getApplied();
        $files = $this->getMigrationFiles($migrationsPath);

        return array_filter($files, fn(string $f) => !isset($applied[basename($f, '.php')]));
    }

    /**
     * @return array<int, string>
     * @throws DatabaseException
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
     * @return array<int, string>
     * @throws DatabaseException
     */
    public function rollback(string $migrationsPath, ?MigrationSchemaSnapshot $snapshot = null): array
    {
        $rolledBack = [];

        foreach (glob($migrationsPath . '/*', GLOB_ONLYDIR) ?: [] as $subDir) {
            $connection = basename($subDir);
            DatabaseConnection::connectTo($connection);
            $db = DatabaseManager::on($connection);
            $runner = new self($db);
            $rolledBack = array_merge($rolledBack, $runner->rollbackForPath($subDir, $db));
        }

        $rolledBack = array_merge($rolledBack, $this->rollbackForPath($migrationsPath, $this->db));

        if ($snapshot !== null && count($rolledBack) > 0) {
            $snapshot->take();
        }

        return $rolledBack;
    }

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
        $pending = $runner->getPending($path);

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

                $this->db->execute(
                    sprintf('INSERT INTO `%s` (connection, migration, batch) VALUES (:connection, :migration, :batch)', self::TABLE),
                    ['connection' => $this->connection, 'migration' => $className, 'batch' => $batch]
                );
            }

            $ran[] = $className;
        }

        if (!$dryRun && $snapshot !== null && $connection === null) {
            $snapshot->take();
        }

        return $ran;
    }

    private function rollbackForPath(string $path, DatabaseManager $db): array
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
            $file = "$path/$className.php";

            if (file_exists($file)) {
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