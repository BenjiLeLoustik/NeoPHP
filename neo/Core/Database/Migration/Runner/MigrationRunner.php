<?php
declare(strict_types=1);

namespace Neo\Core\Database\Migration\Runner;

use Neo\Core\Database\DatabaseManager;
use Neo\Core\Database\Exception\DatabaseException;
use Neo\Core\Database\Migration\MigrationSchemaSnapshot;

final class MigrationRunner
{
    private const string TABLE = 'neo_migrations';

    public function __construct(private DatabaseManager $db)
    {
        $this->ensureTable();
    }

    private function ensureTable(): void
    {
        $this->db->execute(sprintf("
            CREATE TABLE IF NOT EXISTS `%s` (
                `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
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
                'SELECT migration, batch, applied_at FROM `%s` ORDER BY id ASC',
                self::TABLE
            )),
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
            'SELECT MAX(batch) AS last_batch FROM `%s`',
            self::TABLE
        ));

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
        $pending = $this->getPending($migrationsPath);

        if (count($pending) === 0) {
            return [];
        }

        $batch = $this->getLastBatch() + 1;
        $ran = [];

        foreach ($pending as $file) {
            $className = basename($file, '.php');

            if (!$dryRun) {
                require_once $file;

                $instance = new $className();
                $instance->up($this->db);

                $this->db->execute(
                    sprintf('INSERT INTO `%s` (migration, batch) VALUES (:migration, :batch)', self::TABLE),
                    ['migration' => $className, 'batch' => $batch]
                );
            }

            $ran[] = $className;
        }

        if (!$dryRun && $snapshot !== null) {
            $snapshot->take();
        }

        return $ran;
    }

    /**
     * @return array<int, string>
     * @throws DatabaseException
     */
    public function rollback(string $migrationsPath, ?MigrationSchemaSnapshot $snapshot = null): array
    {
        $lastBatch = $this->getLastBatch();

        if ($lastBatch === 0) {
            return [];
        }

        $rows = $this->db->fetchAll(
            sprintf('SELECT migration FROM `%s` WHERE batch = :batch ORDER BY id DESC', self::TABLE),
            ['batch' => $lastBatch]
        );

        $rolledBack = [];

        foreach ($rows as $row) {
            $className = $row['migration'];
            $file = "$migrationsPath/$className.php";

            if (file_exists($file)) {
                require_once $file;

                $instance = new $className();
                $instance->down($this->db);
            }

            $this->db->execute(
                sprintf('DELETE FROM `%s` WHERE migration = :migration', self::TABLE),
                ['migration' => $className]
            );

            $rolledBack[] = $className;
        }

        if ($snapshot !== null && count($rolledBack) > 0) {
            $snapshot->take();
        }

        return $rolledBack;
    }
}