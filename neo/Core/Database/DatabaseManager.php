<?php
declare(strict_types=1);

namespace Neo\Core\Database;

use Neo\Core\Database\Connection\DatabaseConnection;
use Neo\Core\Database\Exception\DatabaseException;
use Neo\Core\DI\Exception\ContainerException;
use PDO;
use PDOException;
use PDOStatement;

class DatabaseManager
{
    private PDO $pdo;
    private ?string $connection;

    /**
     * @throws DatabaseException
     */
    public function __construct(?string $connection = null)
    {
        $this->connection = $connection;
        $this->pdo = DatabaseConnection::getPdo($connection);
    }

    /**
     * @throws DatabaseException
     * @throws ContainerException
     */
    public static function on(string $connection): self
    {
        DatabaseConnection::connectTo($connection);
        return new self($connection);
    }

    public function getConnectionName(): ?string
    {
        return $this->connection ?? DatabaseConnection::getDefaultName();
    }

    /**
     * @param array<string, mixed> $params
     * @throws DatabaseException
     */
    public function query(string $sql, array $params = []): PDOStatement
    {
        try {
            $stmt = $this->pdo->prepare($sql);

            if ($stmt === false) {
                throw new DatabaseException(
                    title: 'Database Query Error',
                    message: sprintf("Unable to prepare query: %s", $sql),
                    code: 500
                );
            }

            $stmt->execute($params);
            return $stmt;
        } catch (PDOException $e) {
            throw new DatabaseException(
                title: 'Database Query Error',
                message: sprintf("Error executing query: %s", $e->getMessage()),
                code: 500,
                previous: $e
            );
        }
    }

    /**
     * @param array<string, mixed> $params
     * @return array<string, mixed>|null
     * @throws DatabaseException
     */
    public function fetch(string $sql, array $params = []): ?array
    {
        $result = $this->query($sql, $params)->fetch();
        return $result !== false ? $result : null;
    }

    /**
     * @param array<string, mixed> $params
     * @return array<int, array<string, mixed>>
     * @throws DatabaseException
     */
    public function fetchAll(string $sql, array $params = []): array
    {
        return $this->query($sql, $params)->fetchAll();
    }

    /**
     * @param array<string|int, mixed> $params
     * @throws DatabaseException
     */
    public function execute(string $sql, array $params = []): bool
    {
        try {
            $stmt = $this->pdo->prepare($sql);

            if ($stmt === false) {
                throw new DatabaseException(
                    title: 'Database Execute Error',
                    message: sprintf("Unable to prepare query: %s", $sql),
                    code: 500
                );
            }

            return $stmt->execute($params);
        } catch (PDOException $e) {
            throw new DatabaseException(
                title: 'Database Execute Error',
                message: sprintf("Error executing query: %s", $e->getMessage()),
                code: 500,
                previous: $e
            );
        }
    }

    public function lastInsertId(): string
    {
        return $this->pdo->lastInsertId();
    }
}