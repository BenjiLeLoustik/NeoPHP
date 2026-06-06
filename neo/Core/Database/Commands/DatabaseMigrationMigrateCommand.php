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
    name: 'database:migration:migrate',
    description: 'Run all pending migrations for a project',
    category: 'Database'
)]
final class DatabaseMigrationMigrateCommand implements CommandInterface
{
    public function __construct(private Container $container) {}

    public function execute(array $args): void
    {
        $project = Args::option($args, '--project');
        $dryRun = Args::flag($args, '--dry-run');

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
            $pending = $runner->getPending($migrationsPath);

            if (empty($pending)) {
                Output::success('Nothing to migrate. Database is up to date.');
                return;
            }

            Output::title('Pending migrations');

            foreach ($pending as $file) {
                Output::muted('  · ' . basename($file));
            }

            Output::newLine();

            if ($dryRun) {
                Output::warning('Dry-run mode: no migration was executed.');
                return;
            }

            if (!Input::confirm(count($pending) . ' migration(s) will be applied. Continue ?', true)) {
                Output::muted('Cancelled.');
                return;
            }

            $ran = $runner->run($migrationsPath, false, $snapshot);

            Output::newLine();

            foreach ($ran as $name) {
                Output::success("Applied: $name");
            }

        } catch (\Throwable $e) {
            Output::error('Migration failed: ' . $e->getMessage());
        }
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

    public function getName(): string
    {
        return 'database:migration:migrate';
    }

    public function getDescription(): string
    {
        return 'Run all pending migrations for a project';
    }

    public function getHelp(): string
    {
        Output::usage('migrate:run', $this->getDescription());
        Output::option('--project=<name>', 'Target project inside ./src/ (interactive selection if omitted)');
        Output::option('--dry-run', 'List pending migrations without executing them');
        Output::newLine();
        echo "  Examples:\n";
        Output::example('php bin/neo migrate:run --project=Blog');
        Output::example('php bin/neo migrate:run --project=Blog --dry-run');
        Output::example('php bin/neo migrate:run');

        return '';
    }
}