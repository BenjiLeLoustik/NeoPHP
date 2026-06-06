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
    name: 'database:migration:status',
    description: 'Show applied and pending migrations for a project',
    category: 'Database'
)]
final class DatabaseMigrationStatusCommand implements CommandInterface
{
    public function __construct(private Container $container) {}

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
            $applied = $runner->getApplied();
            $files = $runner->getMigrationFiles($migrationsPath);

            if (empty($files) && empty($applied)) {
                Output::warning("No migrations found in src/$project/Database/Migrations/");
                return;
            }

            Output::title("Migration status — $project");

            $knownFiles = array_map(fn($f) => basename($f, '.php'), $files);

            foreach ($applied as $name => $row) {
                $inFiles = in_array($name, $knownFiles, true);
                $batch = Output::colorize("batch #{$row['batch']}", 'dim');
                $date = Output::colorize($row['applied_at'], 'dim');
                $status = Output::colorize('✔ applied', 'green');
                $warn = $inFiles ? '' : Output::colorize('  [file missing]', 'yellow');

                echo "  $status  " . str_pad($name, 55) . "  $batch  $date$warn\n";
            }

            foreach ($files as $file) {
                $name = basename($file, '.php');
                if (!isset($applied[$name])) {
                    $status = Output::colorize('· pending', 'yellow');
                    echo "  $status  $name\n";
                }
            }

            Output::newLine();
            $appliedCount = count($applied);
            $pendingCount = count($files) - count(array_filter($files, fn($f) => isset($applied[basename($f, '.php')])));
            Output::muted("  $appliedCount applied · $pendingCount pending");

            if ($snapshot->hasChanged()) {
                Output::newLine();
                Output::warning('Schema has changed since last migration. Run database:migration:generate ?');
            }

        } catch (\Throwable $e) {
            Output::error('Status check failed: ' . $e->getMessage());
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
        return 'migrate:status';
    }

    public function getDescription(): string
    {
        return 'Show applied and pending migrations for a project';
    }

    public function getHelp(): string
    {
        Output::usage('migrate:status', $this->getDescription());
        Output::option('--project=<name>', 'Target project inside ./src/ (interactive selection if omitted)');
        Output::newLine();
        echo "  Examples:\n";
        Output::example('php bin/neo migrate:status --project=Blog');
        Output::example('php bin/neo migrate:status');

        return '';
    }
}