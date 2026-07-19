<?php
declare(strict_types=1);

namespace Neo\Core\Database\Commands;

use Neo\Core\Console\Abstract\AbstractCommand;
use Neo\Core\Console\Attribute\Command;
use Neo\Core\Console\Enum\ExitCode;
use Neo\Core\Console\Input\Input;
use Neo\Core\Console\Input\InputOption;
use Neo\Core\Console\Output\Output;
use PDO;
use PDOException;

#[Command(
    name: 'database:create',
    description: 'Create the database defined in database.config.php for a project',
    category: 'Database',
)]
final class DatabaseCreateCommand extends AbstractCommand
{
    public function configure(): void
    {
        $this->addOption(
            name: 'project',
            shortcut: null,
            mode: InputOption::REQUIRED,
            description: 'Target project',
        );
    }

    public function do(Input $input, Output $output): ExitCode
    {
        $project = $input->getOption('project') ?? Input::choice('Target project ?', $this->getAvailableProjects());
        $configPath = ROOT_DIR . "/src/$project/ConfigManager/database.config.php";

        if (!file_exists($configPath)) {
            Output::error("No database.config.php found.");
            return ExitCode::FAILURE;
        }

        $config = include $configPath;

        if (!($config['enabled'] ?? false)) {
            Output::warning("Database is disabled.");
            return ExitCode::SUCCESS;
        }

        $use = $config['use'] ?? 'default';
        $connection = $config['connections'][$use] ?? null;

        if (!$connection) {
            Output::error("Connection '$use' not found.");
            return ExitCode::FAILURE;
        }

        $driver = $connection['driver'] ?? 'mysql';
        $host = $connection['host'] ?? 'localhost';
        $port = $connection['port'] ?? 3306;
        $dbname = $connection['dbname'] ?? '';
        $user = $connection['user'] ?? '';
        $pass = $connection['pass'] ?? '';
        $charset = $connection['charset'] ?? 'utf8mb4';

        if (!$dbname) {
            Output::error("No 'dbname' defined.");
            return ExitCode::FAILURE;
        }

        Output::label('Driver', $driver);
        Output::label('Host', "$host:$port");
        Output::label('Database', $dbname);
        Output::label('User', $user);

        if (!Input::confirm("Create database '$dbname' ?", false)) {
            Output::muted('Cancelled.');
            return ExitCode::SUCCESS;
        }

        try {
            $dsn = sprintf('%s:host=%s;port=%d;charset=%s', $driver, $host, $port, $charset);
            $pdo = new PDO($dsn, $user, $pass, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);

            $pdo->exec(sprintf(
                'CREATE DATABASE IF NOT EXISTS `%s` CHARACTER SET %s COLLATE %s',
                $dbname,
                $charset,
                $charset . '_unicode_ci'
            ));

            Output::success("Database '$dbname' created successfully.");
            return ExitCode::SUCCESS;
        } catch (PDOException $e) {
            Output::error('Database creation failed: ' . $e->getMessage());
            return ExitCode::FAILURE;
        }
    }

    protected function getAvailableProjects(): array
    {
        return array_map('basename', glob(ROOT_DIR . '/src/*', GLOB_ONLYDIR));
    }
}