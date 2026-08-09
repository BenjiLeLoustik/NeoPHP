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
use Neo\Core\Database\DatabaseManager;
use Neo\Core\Database\Migration\Runner\MigrationRunner;
use Neo\Core\DI\Container;
use Neo\Core\Package\Interface\PackageInterface;

#[Command(
    name: 'database:migration:migrate',
    description: 'Run all pending migrations for a project',
    category: 'Database',
)]
final class DatabaseMigrationMigrateCommand extends AbstractCommand
{
    public function __construct(
        private Container $container
    ) {
    }

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
        $project = $input->getOption('project');
        $dryRun = (bool) $input->getOption('dry-run');

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
            $runner = new MigrationRunner($db, 'default');

            $pending = [];
            foreach ($migrationsPaths as $path) {
                $pending = array_merge($pending, $runner->getPending($path));
            }

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

            $ran = [];
            foreach ($migrationsPaths as $path) {
                $ran = array_merge($ran, $runner->run($path, false));
            }

            foreach ($ran as $name) {
                Output::success("Applied: $name");
            }

            return ExitCode::SUCCESS;
        } catch (\Throwable $e) {
            Output::error('Migration failed: ' . $e->getMessage());
            return ExitCode::FAILURE;
        }
    }

    protected function getAvailableProjects(): array
    {
        return glob(ROOT_DIR . '/src/*', GLOB_ONLYDIR)
                |> (fn (array $d): array => array_map(basename(...), $d));
    }
}