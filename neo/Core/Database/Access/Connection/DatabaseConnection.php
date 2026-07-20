<?php
declare(strict_types=1);

namespace Neo\Core\Database\Access\Connection;

use Neo\Core\Database\Exception\DatabaseException;
use Neo\Core\Database\ORM\ORM;
use Neo\Core\DI\Container;
use Neo\Core\DI\Exception\ContainerException;
use PDO;
use PDOException;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\NotFoundExceptionInterface;

class DatabaseConnection
{
    protected Container $container;

    /** @var array<string, PDO> */
    private static array $connections = [];

    private static ?string $defaultName = null;

    private static ?Container $sharedContainer = null;

    /**
     * @throws NotFoundExceptionInterface
     * @throws ContainerExceptionInterface
     * @throws DatabaseException
     * @throws ContainerException
     */
    public function __construct(Container $container)
    {
        $this->container = $container;
        self::$sharedContainer = $container;

        if (self::$defaultName !== null) {
            return;
        }

        $dbEnabled = $container->get('database.configModule')->from('database')->get('enabled');

        if ($dbEnabled !== true) {
            return;
        }

        $defaultName = $container->get('database.configModule')->from('database')->get('use');
        self::$defaultName = $defaultName;
        self::connectTo($defaultName);
    }

    /**
     * @throws DatabaseException
     * @throws ContainerException
     */
    public static function connectTo(string $name): PDO
    {
        if (isset(self::$connections[$name])) {
            return self::$connections[$name];
        }

        if (self::$sharedContainer === null) {
            throw new DatabaseException(
                title: "Database Connection Error",
                message: "Database connection cannot be established before the container is available",
                code: 500
            );
        }

        $config = self::$sharedContainer->get('database.configModule')->from('database')->get("connections.{$name}");

        if (!is_array($config)) {
            throw new DatabaseException(
                title: "Database Connection Error",
                message: sprintf(
                    "No configuration found for database connection '%s'.",
                    $name
                ),
                code: 500
            );
        }

        $dsn = sprintf(
            '%s:host=%s;port=%d;dbname=%s;charset=%s',
            $config['driver'],
            $config['host'],
            $config['port'] ?? 3306,
            $config['dbname'],
            $config['charset'] ?? 'utf8mb4'
        );

        try {
            $pdo = new PDO(
                $dsn,
                $config['user'],
                $config['pass'],
                [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES => false,
                ]
            );
        } catch (PDOException $e) {
            throw new DatabaseException(
                title: "Database Connection Error",
                message: sprintf("Database connection '%s' failed: %s", $name, $e->getMessage()),
                code: $e->getCode(),
            );
        }

        self::$connections[$name] = $pdo;

        if (count(self::$connections) === 1) {
            $orm = new ORM(self::$sharedContainer);
            $orm->generate();
        }

        return $pdo;

    }

    /**
     * @throws DatabaseException
     */
    public static function getPdo(?string $name = null): PDO
    {
        $name ??= self::$defaultName;

        if ($name === null || !isset(self::$connections[$name])) {
            throw new DatabaseException(
                title: "Database Not Connected",
                message: $name === null
                    ? "No active database connection. Call DatabaseConnection::connectTo() first."
                    : sprintf(
                        "No active database connection named '%s'. Call DatabaseConnection::connectTo('%s') first.",
                        $name,
                        $name
                    ),
                code: 500
            );
        }

        return self::$connections[$name];
    }

    public static function isConnected(?string $name = null): bool
    {
        $name ??= self::$defaultName;

        return $name !== null && isset(self::$connections[$name]);
    }

    public static function getDefaultName(): ?string
    {
        return self::$defaultName;
    }

    /**
     * @return array<int, string>
     */
    public static function getConnectionNames(): array
    {
        return array_keys(self::$connections);
    }
}