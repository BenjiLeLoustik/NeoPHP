<?php
declare(strict_types=1);

namespace Neo\Core\Database\Commands;

use Neo\Core\Console\Abstract\AbstractCommand;
use Neo\Core\Console\Attribute\Command;
use Neo\Core\Console\Enum\ExitCode;
use Neo\Core\Console\Input\Input;
use Neo\Core\Console\Input\InputOption;
use Neo\Core\Console\Output\Output;
use Neo\Core\Database\DatabaseConnection;
use Neo\Core\Database\DatabaseIntrospector;
use Neo\Core\Database\DatabaseManager;
use Neo\Core\Database\Migration\MigrationRunner;
use Neo\Core\Database\Migration\MigrationSchemaSnapshot;
use Neo\Core\DI\Container;

#[Command(
    name: 'database:migration:rollback',
    description: 'Rollback the last batch of migrations for a project',
    category: 'Database',
)]
final class DatabaseMigrationRollbackCommand extends AbstractCommand
{
    public function __construct(
        private readonly Container $container
    ) {}

    public function configure(): void
    {
        $this->addOption(
            name: 'project',
            shortcut: null,
            mode: InputOption::REQUIRED,
            description: 'Target project',
        );

        $this->addOption(
            name: 'force',
            shortcut: null,
            mode: InputOption::NONE,
            description: 'Skip confirmation',
        );
    }

    public function do(Input $input, Output $output): ExitCode
    {
        $project = $input->getOption('project') ?? Input::choice('Target project ?', $this->getAvailableProjects());
        $force = (bool) $input->getOption('force');

        $basePath = ROOT_DIR . "/src/$project";
        $migrationsPath = "$basePath/Database/Migrations";

        if (!is_dir($basePath)) {
            Output::error("Project '$project' not found.");
            return ExitCode::FAILURE;
        }

        try {
            $this->bootProjectContainer($project);
            $this->container->get(DatabaseConnection::class);

            if (!DatabaseConnection::isConnected()) {
                Output::error('Database not connected.');
                return ExitCode::FAILURE;
            }

            $db = new DatabaseManager();
            $snapshot = new MigrationSchemaSnapshot($db, new DatabaseIntrospector($this->container));
            $runner = new MigrationRunner($db);
            $lastBatch = $runner->getLastBatch();

            if ($lastBatch === 0) {
                Output::warning('Nothing to rollback.');
                return ExitCode::SUCCESS;
            }

            $applied = $runner->getApplied();
            $inBatch = array_filter($applied, fn($row) => (int) $row['batch'] === $lastBatch);

            Output::title("Rolling back batch #$lastBatch");

            foreach ($inBatch as $name => $row) {
                $file = $migrationsPath . '/' . $name . '.php';
                $exists = file_exists($file);
                $warn = $exists ? '' : Output::colorize('  [file missing]', 'yellow');
                Output::muted("  · $name$warn");
            }

            if (!$force && !Input::confirm(count($inBatch) . ' migration(s) will be rolled back. Continue ?', false)) {
                Output::muted('Cancelled.');
                return ExitCode::SUCCESS;
            }

            $rolledBack = $runner->rollback($migrationsPath, $snapshot);
            foreach ($rolledBack as $name) {
                Output::success("Rolled back: $name");
            }

            return ExitCode::SUCCESS;
        } catch (\Throwable $e) {
            Output::error('Rollback failed: ' . $e->getMessage());
            return ExitCode::FAILURE;
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

    protected function getAvailableProjects(): array
    {
        return array_map('basename', glob(ROOT_DIR . '/src/*', GLOB_ONLYDIR));
    }
}