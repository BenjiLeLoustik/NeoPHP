<?php
declare(strict_types=1);

namespace Neo\Core\Database\Access\Introspector;

use Neo\Core\Database\Access\Connection\DatabaseConnection;
use Neo\Core\Database\Access\Introspector\Metadata\ColumnMetadata;
use Neo\Core\Database\Access\Introspector\Metadata\ForeignKeyMetadata;
use Neo\Core\Database\Access\Introspector\Metadata\IndexMetadata;
use Neo\Core\Database\Exception\DatabaseException;
use Neo\Core\DI\Container;
use Neo\Core\DI\Exception\ContainerException;
use PDO;
use PDOException;

class DatabaseIntrospector
{
    private PDO $pdo;

    private ?string $connection;

    private string $prefix;

    /**
     * @throws DatabaseException
     * @throws ContainerException
     */
    public function __construct(Container $container, ?string $connection = null)
    {
        $this->connection = $connection;
        $this->pdo = DatabaseConnection::getPdo($this->connection);

        $connectionName = $connection ?? DatabaseConnection::getDefaultName();
        $this->prefix = $container->get('database.configModule')
            ->from('database')
            ->get("connections.{$connectionName}.prefix") ?? '';
    }

    /**
     * @throws DatabaseException
     * @throws ContainerException
     */
    public static function on(Container $container, string $connection): self
    {
        DatabaseConnection::connectTo($connection);
        return new self($container, $connection);
    }

    /**
     * @return list<string>
     * @throws DatabaseException
     */
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
                $tableName = array_first($row);
                if ($this->prefix === '' || str_starts_with($tableName, $this->prefix)) {
                    $tables[] = $tableName;
                }
            }

            $internal = ['neo_migrations', 'neo_schema_snapshots'];
            $tables
                |> (fn (array $t): array => array_filter($t, fn ($x) => !in_array($x, $internal, true)))
                |> array_values(...);

            return $tables;

        } catch (PDOException $e) {
            throw new DatabaseException(
                title: 'Database Introspection Error',
                message: sprintf("Error retrieving tables: %s", $e->getMessage()),
                code: 500,
                previous: $e
            );
        }
    }

    /**
     * @return list<ColumnMetadata>
     * @throws DatabaseException
     */
    public function getColumns(string $table): array
    {
        try {
            $stmt = $this->pdo->prepare("DESCRIBE `{$table}`");
            $stmt->execute();

            $columns = [];
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $columns[] = new ColumnMetadata(
                    name: $row['Field'],
                    type: $row['Type'],
                    nullable: $row['Null'] === 'YES',
                    default: $row['Default'],
                    key: $row['Key'],
                    extra: $row['Extra'],
                );
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

    /**
     * @return list<ForeignKeyMetadata>
     * @throws DatabaseException
     */
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
                $fks[] = new ForeignKeyMetadata(
                    name: $row['name'],
                    column: $row['col'],
                    referencedTable: $row['ref_table'],
                    referencedColumn: $row['ref_col'],
                    onDelete: $row['on_delete'],
                    onUpdate: $row['on_update'],
                );
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

    /**
     * @return list<IndexMetadata>
     * @throws DatabaseException
     */
    public function getIndexes(string $table): array
    {
        try {
            $stmt = $this->pdo->prepare("SHOW INDEX FROM `{$table}`");
            $stmt->execute();

            /** @var array<string, array{unique: bool, columns: array<int, string>}> $grouped */
            $grouped = [];
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $name = $row['Key_name'];
                if ($name === 'PRIMARY') {
                    continue;
                }
                $grouped[$name] ??= [
                    'unique' => ((int) $row['Non_unique']) === 0,
                    'columns' => []
                ];

                $grouped[$name]['columns'][(int) $row['Seq_in_index']] = $row['Column_name'];
            }

            $indexes = [];
            foreach ($grouped as $name => $data) {
                ksort($data['columns']);
                $indexes[] = new IndexMetadata($name, array_values($data['columns']), $data['unique']);
            }

            return $indexes;
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