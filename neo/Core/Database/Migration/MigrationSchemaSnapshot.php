<?php
declare(strict_types=1);

namespace Neo\Core\Database\Migration;

use Neo\Core\Database\DatabaseIntrospector;
use Neo\Core\Database\DatabaseManager;

final class MigrationSchemaSnapshot
{
    private const TABLE = 'neo_schema_snapshots';

    public function __construct(
        private DatabaseManager $db,
        private DatabaseIntrospector $introspector
    ) {
        $this->ensureTable();
    }

    private function ensureTable(): void
    {
        $this->db->execute(sprintf("
            CREATE TABLE IF NOT EXISTS `%s` (
                `id`          INT UNSIGNED NOT NULL AUTO_INCREMENT,
                `schema_hash` VARCHAR(64)  NOT NULL,
                `schema_dump` LONGTEXT     NOT NULL,
                `created_at`  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (`id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ", self::TABLE));
    }

    public function take(): void
    {
        $dump = $this->buildDump();
        $hash = hash('sha256', $dump);

        $this->db->execute(
            sprintf('INSERT INTO `%s` (schema_hash, schema_dump) VALUES (?, ?)', self::TABLE),
            [$hash, $dump]
        );
    }

    public function getLastHash(): ?string
    {
        $row = $this->db->fetch(sprintf(
            'SELECT schema_hash FROM `%s` ORDER BY id DESC LIMIT 1',
            self::TABLE
        ));

        return $row['schema_hash'] ?? null;
    }

    public function getCurrentHash(): string
    {
        return hash('sha256', $this->buildDump());
    }

    public function hasChanged(): bool
    {
        $last = $this->getLastHash();

        if ($last === null) {
            return false;
        }

        return $last !== $this->getCurrentHash();
    }

    private function buildDump(): string
    {
        $tables = $this->introspector->getTables();
        $dump = [];

        foreach ($tables as $table) {
            if (in_array($table, ['neo_migrations', 'neo_schema_snapshots'], true)) {
                continue;
            }

            $columns = $this->introspector->getColumns($table);
            $dump[] = $table . ':' . json_encode($columns);
        }

        sort($dump);

        return implode('|', $dump);
    }
}