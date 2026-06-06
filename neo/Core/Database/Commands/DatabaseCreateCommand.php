<?php
declare(strict_types=1);

namespace Neo\Core\Database\Commands;

use Neo\Core\Console\AbstractCommand;
use Neo\Core\Console\Attribute\Command;
use Neo\Core\Console\Helper\Args;
use Neo\Core\Console\Helper\Input;
use Neo\Core\Console\Helper\Output;
use Neo\Core\DI\Container;
use PDO;
use PDOException;

#[Command(
    name: 'database:create',
    description: 'Create the database defined in database.config.php for a project',
    category: 'Database'
)]
final class DatabaseCreateCommand extends AbstractCommand
{
    public function __construct(
        private Container $container
    ) {}

    public function execute(array $args): void
    {
        $project = Args::option($args, '--project');

        if (!$project) {
            $projects = $this->getAvailableProjects();

            if (empty($projects)) {
                Output::error('No projects found in ./src/');
                return;
            }

            $project = Input::choice('Target project ?', $projects);
        }

        $configPath = ROOT_DIR . "/src/$project/Config/database.config.php";

        if (!file_exists($configPath)) {
            Output::error("No database.config.php found for project '$project'.");
            return;
        }

        $config = include $configPath;

        if (!($config['enabled'] ?? false)) {
            Output::warning("Database is disabled in database.config.php for project '$project'.");
            return;
        }

        $use = $config['use'] ?? 'default';
        $connection = $config['connections'][$use] ?? null;

        if (!$connection) {
            Output::error("Connection '$use' not found in database.config.php.");
            return;
        }

        $driver = $connection['driver'] ?? 'mysql';
        $host = $connection['host'] ?? 'localhost';
        $port = $connection['port'] ?? 3306;
        $dbname = $connection['dbname'] ?? '';
        $user = $connection['user'] ?? '';
        $pass = $connection['pass'] ?? '';
        $charset = $connection['charset'] ?? 'utf8mb4';

        if (!$dbname) {
            Output::error("No 'dbname' defined in database.config.php.");
            return;
        }

        Output::newLine();
        Output::label('Driver', $driver);
        Output::label('Host', "$host:$port");
        Output::label('Database', $dbname);
        Output::label('User', $user);
        Output::newLine();

        if (!Input::confirm("Create database '$dbname' ?", false)) {
            Output::muted('Cancelled.');
            return;
        }

        try {
            $dsn = sprintf('%s:host=%s;port=%d;charset=%s', $driver, $host, $port, $charset);

            $pdo = new PDO($dsn, $user, $pass, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            ]);

            $pdo->exec(
                sprintf(
                    'CREATE DATABASE IF NOT EXISTS `%s` CHARACTER SET %s COLLATE %s',
                    $dbname,
                    $charset,
                    $charset . '_unicode_ci'
                )
            );

            Output::success("Database '$dbname' created successfully.");

        } catch (PDOException $e) {
            Output::error('Database creation failed: ' . $e->getMessage());
        }
    }

    public function getHelp(): string
    {
        Output::usage($this->getName(), $this->getDescription());
        Output::option('--project=<name>', 'Target project inside ./src/ (interactive selection if omitted)');
        Output::newLine();
        echo "  Examples:\n";
        Output::example("php bin/neo {$this->getName()} --project=MyApp");
        Output::example("php bin/neo {$this->getName()}");

        return '';
    }
}