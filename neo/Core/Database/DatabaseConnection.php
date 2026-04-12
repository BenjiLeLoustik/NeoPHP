<?php
declare(strict_types=1);

namespace Neo\Core\Database;

use Neo\Core\Database\ORM\ORM;
use Neo\Core\DI\Container;
use Neo\Core\Error\Exception\FrameworkException;
use Neo\Core\Utils\Config;
use Neo\Core\View\View;
use PDO;
use PDOException;

class DatabaseConnection
{
    protected Container $container;
    private static ?PDO $connection = null;

    public function __construct(Container $container)
    {
        $this->container = $container;

        if (self::$connection !== null) {
            return;
        }

        $dbEnabled   = $this->container->get(Config::class)->from('database')->get('enabled');
        $dbUse       = $this->container->get(Config::class)->from('database')->get('use');
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
                throw new FrameworkException(
                    title: "Database error",
                    message: "Database connection failed: " . $e->getMessage(),
                    code: $e->getCode(),
                );
            }

        }

        $this->container->get(View::class)->registerTwigFunction('database', function () {
            return self::$connection ? 'On' : 'Off';
        });
    }

    public static function getPdo(): PDO
    {
        if (self::$connection === null) {
            throw new FrameworkException(
                title: "Database error",
                message: "Database is not connected. Call DatabaseConnection::connect() first.",
                code: 500
            );
        }

        return self::$connection;
    }
}
