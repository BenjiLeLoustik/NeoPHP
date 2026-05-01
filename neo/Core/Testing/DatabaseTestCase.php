<?php
declare(strict_types=1);

namespace Neo\Core\Testing;

use Neo\App;
use Neo\Core\Database\DatabaseConnection;
use Neo\Core\DI\Container;
use PDO;
use PHPUnit\Framework\TestCase as PHPUnitTestCase;

abstract class DatabaseTestCase extends PHPUnitTestCase
{
    protected Container $container;
    protected PDO $pdo;
    protected static ?App $app = null;

    protected function setUp(): void
    {
        parent::setUp();

        if (static::$app === null) {
            static::$app = new App();
        }

        $this->container = static::$app->getContainer();
        $this->container->get(DatabaseConnection::class);
        $this->pdo = DatabaseConnection::getPdo();

        $this->pdo->beginTransaction();
    }

    protected function tearDown(): void
    {
        if ($this->pdo->inTransaction()) {
            $this->pdo->rollBack();
        }

        parent::tearDown();
    }

    protected function get(string $id): mixed
    {
        return $this->container->get($id);
    }

    protected function swap(string $id, mixed $value): void
    {
        $this->container->set($id, fn() => $value);
    }

    protected function insertFixture(string $table, array $data): int|string
    {
        $columns = implode(', ', array_keys($data));
        $placeholders = implode(', ', array_map(fn($k) => ":$k", array_keys($data)));

        $stmt = $this->pdo->prepare("INSERT INTO $table ($columns) VALUES ($placeholders)");
        $stmt->execute($data);

        return $this->pdo->lastInsertId();
    }

    protected function fetchAll(string $table, string $where = '', array $bindings = []): array
    {
        $sql = "SELECT * FROM $table";
        $sql .= $where ? " WHERE $where" : '';

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($bindings);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    protected function assertDatabaseHas(string $table, array $data): void
    {
        $conditions = implode(' AND ', array_map(fn($k) => "$k = :$k", array_keys($data)));
        $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM $table WHERE $conditions");
        $stmt->execute($data);
        $count = (int) $stmt->fetchColumn();

        $this->assertGreaterThan(
            0,
            $count,
            "Aucune ligne trouvée dans '$table' avec les données : " . json_encode($data)
        );
    }

    protected function assertDatabaseMissing(string $table, array $data): void
    {
        $conditions = implode(' AND ', array_map(fn($k) => "$k = :$k", array_keys($data)));
        $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM $table WHERE $conditions");
        $stmt->execute($data);
        $count = (int) $stmt->fetchColumn();

        $this->assertSame(
            0,
            $count,
            "Une ligne a été trouvée dans '$table' alors qu'elle ne devrait pas exister : " . json_encode($data)
        );
    }
}