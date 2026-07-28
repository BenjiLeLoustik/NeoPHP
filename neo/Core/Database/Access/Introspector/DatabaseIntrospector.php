<?php
declare(strict_types=1);

namespace Neo\Core\Database\Access\Introspector;

use Neo\Core\Database\Access\Connection\DatabaseConnection;
use Neo\Core\Database\Exception\DatabaseException;
use Neo\Core\DI\Container;
use PDO;
use PDOException;

class DatabaseIntrospector
{
    private PDO $pdo;

    private ?string $connection;

    private string $prefix;

    public function __construct(Container $container, ?string $connection = null)
    {
        $this->connection = $connection;
        $this->pdo = DatabaseConnection::getPdo($this->connection);

        $connectionName = $connection ?? DatabaseConnection::getDefaultName();
        $this->prefix = $container->get('database.configModule')->from('database')->get("connections.{$connectionName}.prefix") ?? '';
    }

    public static function on(Container $container, string $connection): self
    {
        DatabaseConnection::connectTo($connection);
        return new self($container, $connection);
    }

    public function getTables(): array
    {
        try {
            $stmt = $this->pdo->query("SHOW TABLES");

            if ($stmt === false) {
                throw new DatabaseException(
                    title: 'Database Introspection Error',
                    message: "Unable to retrieve the list of tables.",
                    code: 500
                );
            }

            $tables = [];
            while ($row = $stmt->fetch(PDO::FETCH_NUM)) {
                $tableName = $row[0];
                if ($this->prefix === '' || str_starts_with($tableName, $this->prefix)) {
                    $tables[] = $tableName;
                }
            }

            $internal = ['neo_migrations', 'neo_schema_snapshots'];
            return array_values(array_filter($tables, fn($t) => !in_array($t, $internal, true)));
        } catch (PDOException $e) {
            throw new DatabaseException(
                title: 'Database Introspection Error',
                message: sprintf("Error retrieving tables: %s", $e->getMessage()),
                code: 500,
                previous: $e
            );
        }
    }

    public function getColumns(string $table): array
    {
        try {
            $stmt = $this->pdo->prepare("DESCRIBE `{$table}`");
            $stmt->execute();

            $columns = [];
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $columns[] = [
                    'name' => $row['Field'],
                    'type' => $row['Type'],
                    'nullable' => $row['Null'] === 'YES',
                    'default' => $row['Default'],
                    'key' => $row['Key'],
                    'extra' => $row['Extra'],
                ];
            }

            return $columns;
        } catch (PDOException $e) {
            throw new DatabaseException(
                title: 'Database Introspection Error',
                message: sprintf("Error retrieving columns for table '%s': %s", $table, $e->getMessage()),
                code: 500,
                previous: $e
            );
        }
    }

    public function getForeignKeys(string $table): array
    {
        $sql = <<<SQL
            SELECT
                k.CONSTRAINT_NAME AS name,
                k.COLUMN_NAME AS col,
                k.REFERENCED_TABLE_NAME AS ref_table,
                k.REFERENCED_COLUMN_NAME AS ref_col,
                r.DELETE_RULE AS on_delete,
                r.UPDATE_RULE AS on_update
            FROM information_schema.KEY_COLUMN_USAGE k
            JOIN information_schema.REFERENTIAL_CONSTRAINTS r
                ON r.CONSTRAINT_NAME = k.CONSTRAINT_NAME
               AND r.CONSTRAINT_SCHEMA = k.TABLE_SCHEMA
            WHERE k.TABLE_SCHEMA = DATABASE()
              AND k.TABLE_NAME = :table
              AND k.REFERENCED_TABLE_NAME IS NOT NULL
            ORDER BY k.CONSTRAINT_NAME, k.ORDINAL_POSITION
        SQL;

        try {
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute(['table' => $table]);

            $fks = [];
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $fks[] = [
                    'name' => $row['name'],
                    'column' => $row['col'],
                    'referencedTable' => $row['ref_table'],
                    'referencedColumn' => $row['ref_col'],
                    'onDelete' => $row['on_delete'],
                    'onUpdate' => $row['on_update'],
                ];
            }

            return $fks;
        } catch (PDOException $e) {
            throw new DatabaseException(
                title: 'Database Introspection Error',
                message: sprintf("Error retrieving foreign keys for table '%s': %s", $table, $e->getMessage()),
                code: 500,
                previous: $e
            );
        }
    }

    public function getIndexes(string $table): array
    {
        try {
            $stmt = $this->pdo->prepare("SHOW INDEX FROM `{$table}`");
            $stmt->execute();

            $grouped = [];
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $name = $row['Key_name'];
                if ($name === 'PRIMARY') {
                    continue;
                }
                $grouped[$name] ??= ['name' => $name, 'columns' => [], 'unique' => ((int) $row['Non_unique']) === 0];
                $grouped[$name]['columns'][(int) $row['Seq_in_index']] = $row['Column_name'];
            }

            return array_values(array_map(static function (array $index): array {
                ksort($index['columns']);
                $index['columns'] = array_values($index['columns']);
                return $index;
            }, $grouped));
        } catch (PDOException $e) {
            throw new DatabaseException(
                title: 'Database Introspection Error',
                message: sprintf("Error retrieving indexes for table '%s': %s", $table, $e->getMessage()),
                code: 500,
                previous: $e
            );
        }
    }
}