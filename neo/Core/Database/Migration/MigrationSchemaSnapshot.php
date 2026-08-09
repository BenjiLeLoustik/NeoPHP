<?php
declare(strict_types=1);

namespace Neo\Core\Database\Migration;

use Neo\Core\Database\Access\Introspector\DatabaseIntrospector;
use Neo\Core\Database\Access\Introspector\Metadata\ColumnMetadata;
use Neo\Core\Database\DatabaseManager;
use Neo\Core\Database\Exception\DatabaseException;

final class MigrationSchemaSnapshot
{
    private const string TABLE = 'neo_schema_snapshots';

    public function __construct(
        private DatabaseManager $db,
        private DatabaseIntrospector $introspector,
        private string $connection = 'default',
    ) {
        $this->ensureTable();
    }

    private function ensureTable(): void
    {
        $this->db->execute(sprintf("
            CREATE TABLE IF NOT EXISTS `%s` (
                `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
                `connection` VARCHAR(50) NOT NULL DEFAULT 'default',
                `schema_hash` VARCHAR(64) NOT NULL,
                `schema_dump` LONGTEXT NOT NULL,
                `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (`id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ", self::TABLE));
    }

    /**
     * @throws DatabaseException
     */
    public function take(): void
    {
        $dump = $this->buildDump();
        $hash = hash('sha256', $dump);

        $this->db->execute(
            sprintf(
                'INSERT INTO `%s` (connection, schema_hash, schema_dump) VALUES (:connection, :hash, :dump)',
                self::TABLE
            ),
            [
                'connection' => $this->connection,
                'hash' => $hash,
                'dump' => $dump,
            ]
        );
    }

    /**
     * @return array<string, mixed>|null
     */
    public function getLastSchema(): ?array
    {
        $row = $this->db->fetch(sprintf(
            'SELECT schema_dump FROM `%s` WHERE connection = :connection ORDER BY id DESC LIMIT 1',
            self::TABLE
        ), ['connection' => $this->connection]);

        if ($row === null || !isset($row['schema_dump'])) {
            return null;
        }

        $decoded = json_decode($row['schema_dump'], true);

        return is_array($decoded) ? $decoded : null;
    }

    /**
     * @return array<string, list<array{
     *     name: string,
     *     type: string,
     *     nullable: bool,
     *     default: string|null,
     *     key: string,
     *     extra: string
     * }>>
     */
    public function getCurrentSchema(): array
    {
        return $this->buildDumpArray();
    }

    /**
     * @return array<string, list<array{
     *     name: string,
     *     type: string,
     *     nullable: bool,
     *     default: string|null,
     *     key: string,
     *     extra: string
     * }>>
     * @throws DatabaseException
     */
    private function buildDumpArray(): array
    {
        $tables = $this->introspector->getTables();
        $dump = [];

        foreach ($tables as $table) {
            if (in_array($table, ['neo_migrations', self::TABLE], true)) {
                continue;
            }

            $dump[$table] = array_map(
                static fn (ColumnMetadata $column): array => $column->toArray(),
                $this->introspector->getColumns($table)
            );
        }

        ksort($dump);

        return $dump;
    }

    /**
     * @throws DatabaseException
     */
    private function buildDump(): string
    {
        return json_encode(
            $this->buildDumpArray(),
            JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
        );
    }
}