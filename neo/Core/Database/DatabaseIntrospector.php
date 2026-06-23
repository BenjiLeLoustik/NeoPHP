<?php
declare(strict_types=1);

namespace Neo\Core\Database;

use Neo\Core\Database\Exception\DatabaseException;
use Neo\Core\DI\Container;
use Neo\Core\DI\Exception\ContainerException;
use Neo\Core\Error\Exception\FrameworkException;
use PDO;
use PDOException;

class DatabaseIntrospector
{
    private PDO $pdo;

    private ?string $connection;

    /**
     * @throws DatabaseException
     */
    public function __construct(Container $container, ?string $connection = null)
    {
        $this->connection = $connection;
        $this->pdo = DatabaseConnection::getPdo($this->connection);
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
     * @return array<int, string>
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
                $tables[] = $row[0];
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

    /**
     * @return array<int, array{name: string, type: string, nullable: bool, default: mixed, key: string, extra: string}>
     * @throws DatabaseException
     */
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
}