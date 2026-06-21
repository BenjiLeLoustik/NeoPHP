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
    name: 'database:migration:migrate',
    description: 'Run all pending migrations for a project',
    category: 'Database',
)]
final class DatabaseMigrationMigrateCommand extends AbstractCommand
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
            name: 'dry-run',
            shortcut: null,
            mode: InputOption::NONE,
            description: 'List pending migrations without executing',
        );
    }

    public function do(Input $input, Output $output): ExitCode
    {
        $project = $input->getOption('project') ?? Input::choice('Target project ?', $this->getAvailableProjects());
        $dryRun = (bool) $input->getOption('dry-run');

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
            $pending = $runner->getPending($migrationsPath);

            if (empty($pending)) {
                Output::success('Nothing to migrate.');
                return ExitCode::SUCCESS;
            }

            Output::title('Pending migrations');
            foreach ($pending as $file) {
                Output::muted('  · ' . basename($file));
            }

            if ($dryRun) {
                Output::warning('Dry-run mode: nothing executed.');
                return ExitCode::SUCCESS;
            }

            if (!Input::confirm(count($pending) . ' migration(s) will be applied. Continue ?', true)) {
                Output::muted('Cancelled.');
                return ExitCode::SUCCESS;
            }

            $ran = $runner->run($migrationsPath, false, $snapshot);
            foreach ($ran as $name) {
                Output::success("Applied: $name");
            }

            return ExitCode::SUCCESS;
        } catch (\Throwable $e) {
            Output::error('Migration failed: ' . $e->getMessage());
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