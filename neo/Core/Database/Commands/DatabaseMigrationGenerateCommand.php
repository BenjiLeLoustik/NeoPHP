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
use Neo\Core\Database\Migration\MigrationGenerator;
use Neo\Core\Database\Migration\MigrationSchemaSnapshot;
use Neo\Core\Database\Migration\SchemaDiffer;
use Neo\Core\DI\Container;

#[Command(
    name: 'database:migration:generate',
    description: 'Generate a migration file from the current database schema',
    category: 'Database',
)]
final class DatabaseMigrationGenerateCommand extends AbstractCommand
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
            name: 'name',
            shortcut: null,
            mode: InputOption::REQUIRED,
            description: 'Migration name slug',
        );
    }

    public function do(Input $input, Output $output): ExitCode
    {
        $project = $input->getOption('project') ?? Input::choice('Target project ?', $this->getAvailableProjects());
        $name = $input->getOption('name') ?? Input::ask('Migration name ?', 'schema_update');

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

            $introspector = new DatabaseIntrospector($this->container);
            $tables = $introspector->getTables();

            if (empty($tables)) {
                Output::warning('No tables found.');
                return ExitCode::SUCCESS;
            }

            $db = $this->container->get(DatabaseManager::class);
            $snapshot = new MigrationSchemaSnapshot($db, $introspector);
            $generator = new MigrationGenerator($introspector);

            $previousSchema = $snapshot->getLastSchema();

            if ($previousSchema === null) {
                Output::info('No previous snapshot found. Generating full schema migration.');
                $file = $generator->generate($migrationsPath, $name);
                $snapshot->take();

                Output::success('Migration file generated:');
                Output::muted('  ' . str_replace(ROOT_DIR, '', $file));
                return ExitCode::SUCCESS;
            }

            $currentSchema = $snapshot->getCurrentSchema();
            $differ = new SchemaDiffer();
            $diff = $differ->diff($previousSchema, $currentSchema);

            if ($differ->isEmpty($diff)) {
                Output::info('No schema changes detected. Nothing to migrate.');
                return ExitCode::SUCCESS;
            }

            $this->printDiffSummary($diff);

            $file = $generator->generateDiff($migrationsPath, $name, $diff);
            $snapshot->take();

            Output::success('Migration file generated:');
            Output::muted('  ' . str_replace(ROOT_DIR, '', $file));

            return ExitCode::SUCCESS;
        } catch (\Throwable $e) {
            Output::error('Generation failed: ' . $e->getMessage());
            return ExitCode::FAILURE;
        }
    }

    /**
     * @param array{
     *     tablesToCreate: array<string, mixed>,
     *     tablesToDrop: array<string, mixed>,
     *     tableChanges: array<string, array{added: array<int, array<string,mixed>>, removed: array<int, array<string,mixed>>, modified: array<int, array{before: array<string,mixed>, after: array<string,mixed>}>}>
     * } $diff
     */
    private function printDiffSummary(array $diff): void
    {
        foreach (array_keys($diff['tablesToCreate']) as $table) {
            Output::success("  + table `$table`");
        }

        foreach (array_keys($diff['tablesToDrop']) as $table) {
            Output::warning("  - table `$table`");
        }

        foreach ($diff['tableChanges'] as $table => $changes) {
            foreach ($changes['added'] as $col) {
                Output::success("  + {$table}.{$col['name']}");
            }
            foreach ($changes['removed'] as $col) {
                Output::warning("  - {$table}.{$col['name']}");
            }
            foreach ($changes['modified'] as $change) {
                Output::info("  ~ {$table}.{$change['after']['name']}");
            }
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
        $this->container->set('repositoryPath', "$srcPath/Database/Repository");
        $this->container->set('modelPath', "$srcPath/Database/Model");
        $this->container->set('formPath', "$srcPath/Database/Forms");
        $this->container->set('modelNamespace', "Neo\\Src\\$project\\Database\\Model");
        $this->container->set('repositoryNamespace', "Neo\\Src\\$project\\Database\\Repository");
        $this->container->set('formNamespace', "Neo\\Src\\$project\\Database\\Forms");
    }

    protected function getAvailableProjects(): array
    {
        return array_map('basename', glob(ROOT_DIR . '/src/*', GLOB_ONLYDIR));
    }
}