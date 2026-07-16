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
use Neo\Core\Database\DatabaseConnection;
use Neo\Core\Database\DatabaseIntrospector;
use Neo\Core\Database\DatabaseManager;
use Neo\Core\Database\Migration\MigrationRunner;
use Neo\Core\Database\Migration\MigrationSchemaSnapshot;
use Neo\Core\DI\Container;

#[Command(
    name: 'database:migration:status',
    description: 'Show applied and pending migrations for a project',
    category: 'Database',
)]
final class DatabaseMigrationStatusCommand extends AbstractCommand
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
    }

    public function do(Input $input, Output $output): ExitCode
    {
        $project = $input->getOption('project') ?? Input::choice('Target project ?', $this->getAvailableProjects());

        $basePath = ROOT_DIR . "/src/$project";
        $migrationsPath = "$basePath/Database/Migrations";

        if (!is_dir($basePath)) {
            Output::error("Project '$project' not found.");
            return ExitCode::FAILURE;
        }

        try {
            new ApplicationPaths($this->container)->register($project);
            $this->container->get(DatabaseConnection::class);

            if (!DatabaseConnection::isConnected()) {
                Output::error('Database not connected.');
                return ExitCode::FAILURE;
            }

            $db = new DatabaseManager();
            $introspector = new DatabaseIntrospector($this->container);
            $snapshot = new MigrationSchemaSnapshot($db, $introspector);
            $runner = new MigrationRunner($db);
            $applied = $runner->getApplied();
            $files = $runner->getMigrationFiles($migrationsPath);

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
                Output::warning('Schema has changed. Run database:migration:generate ?');
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