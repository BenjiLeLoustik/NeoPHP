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
    private static bool $schemaSynced = false;

    protected function setUp(): void
    {
        parent::setUp();

        if (static::$app === null) {
            static::$app = new App();
        }

        $this->container = static::$app->getContainer();
        $this->container->get(DatabaseConnection::class);
        $this->pdo = DatabaseConnection::getPdo();

        if (!self::$schemaSynced) {
            $this->syncSchema();
            self::$schemaSynced = true;
        }

        $this->pdo->beginTransaction();
    }

    protected function tearDown(): void
    {
        if ($this->pdo->inTransaction()) {
            $this->pdo->rollBack();
        }

        parent::tearDown();
    }

    private function syncSchema(): void
    {
        $configsPath = $this->container->get('configsPath');
        $devConfig = require $configsPath . '/database.config.php';
        $useDriver = $devConfig['use'];
        $connConfig = $devConfig['connections'][$useDriver];

        $devDsn = sprintf(
            '%s:host=%s;dbname=%s;charset=%s',
            $connConfig['driver'],
            $connConfig['host'],
            $connConfig['dbname'],
            $connConfig['charset']
        );

        $devPdo = new PDO(
            $devDsn,
            $connConfig['user'],
            $connConfig['pass'],
            [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            ]
        );

        $tables = $devPdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
        foreach ($tables as $table) {
            $row = $devPdo->query("SHOW CREATE TABLE `$table`")->fetch(PDO::FETCH_ASSOC);
            $createSql = $row['Create Table'];
            $createSql = preg_replace('/^CREATE TABLE (`[^`]+`)/', 'CREATE TABLE IF NOT EXISTS $1', $createSql);
            $this->pdo->exec($createSql);
        }
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