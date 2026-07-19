<?php
declare(strict_types=1);

namespace Neo\Core\Database\Access\Facade;

use Neo\Core\Database\Access\Connection\DatabaseConnection;
use Neo\Core\Database\DatabaseManager;
use Neo\Core\Database\Exception\DatabaseException;
use Neo\Core\DI\Exception\ContainerException;
use PDO;
use PDOStatement;

final class DatabaseFacade
{
    public function isConnected(?string $name = null): bool
    {
        return DatabaseConnection::isConnected($name);
    }

    /**
     * @return array<int, string>
     */
    public function getConnectionNames(): array
    {
        return DatabaseConnection::getConnectionNames();
    }

    public function getDefaultName(): ?string
    {
        return DatabaseConnection::getDefaultName();
    }

    /**
     * @throws DatabaseException
     * @throws ContainerException
     */
    public function connect(string $name): PDO
    {
        return DatabaseConnection::connectTo($name);
    }

    /**
     * @throws DatabaseException
     */
    public function getPdo(?string $name = null): PDO
    {
        return DatabaseConnection::getPdo($name);
    }

    /**
     * @throws DatabaseException
     * @throws ContainerException
     */
    public function manager(?string $name = null): DatabaseManager
    {
        return $name !== null ? DatabaseManager::on($name) : new DatabaseManager();
    }

    /**
     * Shortcut: same as manager($connection), kept for readability at call sites.
     *
     * @throws DatabaseException
     * @throws ContainerException
     */
    public function on(string $connection): DatabaseManager
    {
        return $this->manager($connection);
    }

    /**
     * @param array<string, mixed> $params
     * @throws DatabaseException
     * @throws ContainerException
     */
    public function query(string $sql, array $params = [], ?string $connection = null): PDOStatement
    {
        return $this->manager($connection)->query($sql, $params);
    }

    /**
     * @param array<string, mixed> $params
     * @return array<string, mixed>|null
     * @throws DatabaseException
     * @throws ContainerException
     */
    public function fetch(string $sql, array $params = [], ?string $connection = null): ?array
    {
        return $this->manager($connection)->fetch($sql, $params);
    }

    /**
     * @param array<string, mixed> $params
     * @return array<int, array<string, mixed>>
     * @throws DatabaseException
     * @throws ContainerException
     */
    public function fetchAll(string $sql, array $params = [], ?string $connection = null): array
    {
        return $this->manager($connection)->fetchAll($sql, $params);
    }

    /**
     * @param array<string|int, mixed> $params
     * @throws DatabaseException
     * @throws ContainerException
     */
    public function execute(string $sql, array $params = [], ?string $connection = null): bool
    {
        return $this->manager($connection)->execute($sql, $params);
    }
}