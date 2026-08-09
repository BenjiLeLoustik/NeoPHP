<?php

namespace Neo\Core\Database;

use Neo\Core\Database\Access\Connection\DatabaseConnection;
use Neo\Core\Database\Exception\DatabaseException;
use Neo\Core\DI\Exception\ContainerException;
use Neo\Core\Profiler\TimelineRecorder;
use PDO;
use PDOException;
use PDOStatement;

class DatabaseManager
{
    private PDO $pdo;
    private ?string $connection;

    /** @var list<array{sql: string, params: array<string|int, mixed>, duration: float, connection: string|null, time: float, error: string|null}> */
    private static array $queries = [];

    public function __construct(?string $connection = null)
    {
        $this->connection = $connection;
        $this->pdo = DatabaseConnection::getPdo($connection);
    }

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
     * @param array<string|int, mixed> $params
     */
    public function query(string $sql, array $params = []): PDOStatement
    {
        $start = microtime(true);

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
            $this->recordQuery($sql, $params, $start);
            return $stmt;
        } catch (PDOException $e) {
            $this->recordQuery($sql, $params, $start, $e->getMessage());
            throw new DatabaseException(
                title: 'Database Query Error',
                message: sprintf("Error executing query: %s", $e->getMessage()),
                code: 500,
                previous: $e
            );
        }
    }

    /**
     * @param array<string|int, mixed> $params
     * @return array<string, mixed>|null
     */
    public function fetch(string $sql, array $params = []): ?array
    {
        $result = $this->query($sql, $params)->fetch();
        return $result !== false
            ? $result
            : null;
    }

    /**
     * @param array<string|int, mixed> $params
     * @return list<array<string, mixed>>
     */
    public function fetchAll(string $sql, array $params = []): array
    {
        return $this->query($sql, $params)->fetchAll();
    }

    /**
     * @param array<string|int, mixed> $params
     */
    public function execute(string $sql, array $params = []): bool
    {
        $start = microtime(true);

        try {
            $stmt = $this->pdo->prepare($sql);

            if ($stmt === false) {
                throw new DatabaseException(
                    title: 'Database Execute Error',
                    message: sprintf("Unable to prepare query: %s", $sql),
                    code: 500
                );
            }

            $result = $stmt->execute($params);
            $this->recordQuery($sql, $params, $start);
            return $result;
        } catch (PDOException $e) {
            $this->recordQuery($sql, $params, $start, $e->getMessage());
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

    /**
     * @param array<string|int, mixed> $params
     */
    private function recordQuery(string $sql, array $params, float $start, ?string $error = null): void
    {
        self::$queries[] = [
            'sql' => $sql,
            'params' => $params,
            'duration' => round((microtime(true) - $start) * 1000, 2),
            'connection' => $this->connection,
            'time' => $start,
            'error' => $error,
        ];

        if (class_exists(TimelineRecorder::class)) {
            TimelineRecorder::record('database', substr($sql, 0, 80), $start);
        }
    }

    /**
     * @return list<array{sql: string, params: array<string|int, mixed>, duration: float, connection: string|null, time: float, error: string|null}>
     */
    public static function getQueries(): array
    {
        return self::$queries;
    }
}