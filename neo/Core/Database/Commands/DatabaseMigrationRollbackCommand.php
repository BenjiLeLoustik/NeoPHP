<?php
declare(strict_types=1);

namespace Neo\Core\Database\Commands;

use Neo\Core\Console\Attribute\Command;
use Neo\Core\Console\Helper\Args;
use Neo\Core\Console\Helper\Input;
use Neo\Core\Console\Helper\Output;
use Neo\Core\Console\Interface\CommandInterface;
use Neo\Core\Database\DatabaseConnection;
use Neo\Core\Database\DatabaseIntrospector;
use Neo\Core\Database\DatabaseManager;
use Neo\Core\Database\Migration\MigrationRunner;
use Neo\Core\Database\Migration\MigrationSchemaSnapshot;
use Neo\Core\DI\Container;

#[Command(
    name: 'database:migration:rollback',
    description: 'Rollback the last batch of migrations for a project',
    category: 'Database'
)]
final class DatabaseMigrationRollbackCommand implements CommandInterface
{
    public function __construct(private Container $container) {}

    public function execute(array $args): void
    {
        $project = Args::option($args, '--project');
        $force = Args::flag($args, '--force');

        if (!$project) {
            $projects = $this->getAvailableProjects();

            if (empty($projects)) {
                Output::error('No projects found in ./src/');
                return;
            }

            $project = Input::choice('Target project ?', $projects);
        }

        $basePath = ROOT_DIR . "/src/$project";
        $migrationsPath = "$basePath/Database/Migrations";

        if (!is_dir($basePath)) {
            Output::error("Project '$project' does not exist inside ./src/");
            return;
        }

        try {
            $this->bootProjectContainer($project);
            $this->container->get(DatabaseConnection::class);

            if (!DatabaseConnection::isConnected()) {
                Output::error('Database is not connected. Check database.config.php.');
                return;
            }

            $db = new DatabaseManager();
            $snapshot = new MigrationSchemaSnapshot($db, new DatabaseIntrospector($this->container));
            $runner = new MigrationRunner($db);
            $lastBatch = $runner->getLastBatch();

            if ($lastBatch === 0) {
                Output::warning('Nothing to rollback. No migrations have been applied.');
                return;
            }

            $applied = $runner->getApplied();
            $inBatch = array_filter($applied, fn($row) => (int) $row['batch'] === $lastBatch);

            Output::title("Rolling back batch #$lastBatch");

            foreach ($inBatch as $name => $row) {
                $file = $migrationsPath . '/' . $name . '.php';
                $exists = file_exists($file);
                $warn = $exists ? '' : Output::colorize('  [file missing — only untracked]', 'yellow');
                Output::muted("  · $name$warn");
            }

            Output::newLine();

            if (!$force && !Input::confirm(count($inBatch) . ' migration(s) will be rolled back. Continue ?', false)) {
                Output::muted('Cancelled.');
                return;
            }

            $rolledBack = $runner->rollback($migrationsPath, $snapshot);

            Output::newLine();

            foreach ($rolledBack as $name) {
                Output::success("Rolled back: $name");
            }

        } catch (\Throwable $e) {
            Output::error('Rollback failed: ' . $e->getMessage());
        }
    }


    private function bootProjectContainer(string $project): void
    {
        $srcPath = ROOT_DIR . "/src/$project";

        $this->container->set('application', $project);
        $this->container->set('projectPath', $srcPath);
        $this->container->set('controllerNamespace', "Neo\\Src\\$project\\App\\Controllers");
        $this->container->set('controllersPath', "$srcPath/App/Controllers");
        $this->container->set('storagePath', "$srcPath/Storage");
        $this->container->set('configsPath', "$srcPath/Config");
        $this->container->set('repositoryPath', "$srcPath/Repository");
        $this->container->set('modelPath', "$srcPath/Model");
        $this->container->set('formPath', "$srcPath/App/Forms");
        $this->container->set('modelNamespace', "Neo\\Src\\$project\\Model");
        $this->container->set('repositoryNamespace', "Neo\\Src\\$project\\Repository");
        $this->container->set('formNamespace', "Neo\\Src\\$project\\App\\Forms");
    }

    private function getAvailableProjects(): array
    {
        $srcDir = ROOT_DIR . '/src/';

        if (!is_dir($srcDir)) {
            return [];
        }

        return array_map(
            fn(string $dir) => basename($dir),
            glob($srcDir . '*', GLOB_ONLYDIR) ?: []
        );
    }

    public function getName(): string
    {
        return 'database:migration:rollback';
    }

    public function getDescription(): string
    {
        return 'Rollback the last batch of migrations for a project';
    }

    public function getHelp(): string
    {
        Output::usage('migrate:rollback', $this->getDescription());
        Output::option('--project=<name>', 'Target project inside ./src/ (interactive selection if omitted)');
        Output::option('--force', 'Skip confirmation prompt');
        Output::newLine();
        echo "  Examples:\n";
        Output::example('php bin/neo migrate:rollback --project=Blog');
        Output::example('php bin/neo migrate:rollback --project=Blog --force');
        Output::example('php bin/neo migrate:rollback');

        return '';
    }
}