<?php
declare(strict_types=1);

namespace Neo\Core\Database\Commands;

use Neo\Core\Application\ApplicationPaths;
use Neo\Core\Console\Abstract\AbstractCommand;
use Neo\Core\Console\Attribute\Command;
use Neo\Core\Console\Enum\ExitCode;
use Neo\Core\Console\Input\Input;
use Neo\Core\Console\Input\InputOption;
use Neo\Core\Console\Output\Output;
use Neo\Core\Database\Access\Connection\DatabaseConnection;
use Neo\Core\Database\Access\Introspector\DatabaseIntrospector;
use Neo\Core\Database\DatabaseManager;
use Neo\Core\Database\Migration\MigrationSchemaSnapshot;
use Neo\Core\Database\Migration\Runner\MigrationRunner;
use Neo\Core\DI\Container;
use Neo\Core\Package\Interface\PackageInterface;

#[Command(
    name: 'database:migration:status',
    description: 'Show applied and pending migrations for a project',
    category: 'Database',
)]
final class DatabaseMigrationStatusCommand extends AbstractCommand
{
    public function __construct(
        private Container $container
    ) {}

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
        $project = $input->getOption('project');
        $basePath = ROOT_DIR . "/src/$project";
        $migrationsPaths = ["$basePath/Database/Migrations"];

        if (!is_dir($basePath)) {
            Output::error("Project '$project' not found.");
            return ExitCode::FAILURE;
        }

        try {
            new ApplicationPaths($this->container)->register($project);
            $this->container->get(DatabaseConnection::class);

            if ($this->container->has('packages')) {
                /** @var array<int, PackageInterface> $packages */
                $packages = $this->container->get('packages');

                foreach ($packages as $package) {
                    $path = $package->getMigrationsPath();
                    if ($path !== null) {
                        $migrationsPaths[] = $path;
                    }
                }
            }

            if (!DatabaseConnection::isConnected()) {
                Output::error('Database not connected.');
                return ExitCode::FAILURE;
            }

            $db = new DatabaseManager();
            $introspector = new DatabaseIntrospector($this->container);
            $snapshot = new MigrationSchemaSnapshot($db, $introspector);
            $runner = new MigrationRunner($db);
            $applied = $runner->getApplied();

            $files = [];
            foreach ($migrationsPaths as $path) {
                $files = array_merge($files, $runner->getMigrationFiles($path));
            }

            if (empty($files) && empty($applied)) {
                Output::warning('No migrations found.');
                return ExitCode::SUCCESS;
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
                    echo "  " . Output::colorize('· pending', 'yellow') . "  $name\n";
                }
            }

            Output::newLine();
            $appliedCount = count($applied);
            $pendingCount = count($files) - count(array_filter($files, fn($f) => isset($applied[basename($f, '.php')])));
            Output::muted("  $appliedCount applied · $pendingCount pending");

            $lastSchema = $snapshot->getLastSchema();

            if ($lastSchema !== null && $lastSchema !== $snapshot->getCurrentSchema()) {
                Output::newLine();
                Output::warning('Schema has changed. Run database:orm:diff ?');
            }

            return ExitCode::SUCCESS;
        } catch (\Throwable $e) {
            Output::error('Status check failed: ' . $e->getMessage());
            return ExitCode::FAILURE;
        }
    }

    protected function getAvailableProjects(): array
    {
        return array_map('basename', glob(ROOT_DIR . '/src/*', GLOB_ONLYDIR));
    }
}