<?php
declare(strict_types=1);

namespace Neo\Core\Database;

use Neo\Core\Database\Exception\DatabaseException;
use Neo\Core\Database\ORM\ORM;
use Neo\Core\DI\Container;
use Neo\Core\Utils\Config\Config;
use Neo\Core\View\View;
use PDO;
use PDOException;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\NotFoundExceptionInterface;

class DatabaseConnection
{
    protected Container $container;
    private static ?PDO $connection = null;

    /**
     * @throws NotFoundExceptionInterface
     * @throws ContainerExceptionInterface
     * @throws DatabaseException
     */
    public function __construct(Container $container)
    {
        $this->container = $container;

        if (self::$connection !== null) {
            return;
        }

        $dbEnabled = $this->container->get(Config::class)->from('database')->get('enabled');
        $dbUse = $this->container->get(Config::class)->from('database')->get('use');
        $dbUseConfig = $this->container->get(Config::class)->from('database')->get("connections.$dbUse");

        if ($dbEnabled === true) {

            $dsn = sprintf(
                '%s:host=%s;dbname=%s;charset=%s',
                $dbUseConfig['driver'],
                $dbUseConfig['host'],
                $dbUseConfig['dbname'],
                $dbUseConfig['charset'] ?? 'utf8mb4'
            );

            try {
                self::$connection = new PDO(
                    $dsn,
                    $dbUseConfig['user'],
                    $dbUseConfig['pass'],
                    [
                        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                        PDO::ATTR_EMULATE_PREPARES => false,
                    ]
                );

                $orm = new ORM($this->container);
                $orm->generate();

            } catch (PDOException $e) {
                throw new DatabaseException(
                    title: "Database Connection Error",
                    message: sprintf("Database connection failed: %s", $e->getMessage()),
                    code: $e->getCode(),
                );
            }

        }
    }

    /**
     * @throws DatabaseException
     */
    public static function getPdo(): PDO
    {
        if (self::$connection === null) {
            throw new DatabaseException(
                title: "Database Not Connected",
                message: "No active database connection. Call DatabaseConnection::connect() first.",
                code: 500
            );
        }

        return self::$connection;
    }

    public static function isConnected(): bool
    {
        return self::$connection !== null;
    }
}
