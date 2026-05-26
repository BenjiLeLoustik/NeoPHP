<?php
declare(strict_types=1);

namespace Neo\Core\Database;

use Neo\Core\Database\Exception\DatabaseException;
use Neo\Core\Error\Exception\FrameworkException;
use PDO;
use PDOException;
use PDOStatement;

class DatabaseManager
{
    private PDO $pdo;

    public function __construct()
    {
        $this->pdo = DatabaseConnection::getPdo();
    }

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

    public function fetch(string $sql, array $params = []): ?array
    {
        $result = $this->query($sql, $params)->fetch();
        return $result !== false ? $result : null;
    }

    public function fetchAll(string $sql, array $params = []): array
    {
        return $this->query($sql, $params)->fetchAll();
    }

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