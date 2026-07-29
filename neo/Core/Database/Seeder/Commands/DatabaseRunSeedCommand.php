<?php
declare(strict_types=1);

namespace Neo\Core\Database\Seeder\Commands;

use Neo\Core\Application\ApplicationPaths;
use Neo\Core\Console\Abstract\AbstractCommand;
use Neo\Core\Console\Attribute\Command;
use Neo\Core\Console\Enum\ExitCode;
use Neo\Core\Console\Input\Input;
use Neo\Core\Console\Input\InputOption;
use Neo\Core\Console\Output\Output;
use Neo\Core\Database\Access\Connection\DatabaseConnection;
use Neo\Core\Database\Seeder\SeedManager;
use Neo\Core\DI\Container;

#[Command(
    name: 'database:run:seed',
    description: 'Run the seeders of a project',
    category: 'Database',
)]
final class DatabaseRunSeedCommand extends AbstractCommand
{
    public function __construct(
        private readonly Container $container,
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
            name: 'group',
            shortcut: null,
            mode: InputOption::OPTIONAL,
            description: 'Only run seeders of this group',
            default: null,
        );

        $this->addOption(
            name: 'dev',
            shortcut: null,
            mode: InputOption::NONE,
            description: 'Include demo/dev seeders (all groups)',
        );

        $this->addOption(
            name: 'dry-run',
            shortcut: null,
            mode: InputOption::NONE,
            description: 'List the seeders that would run without executing them',
        );
    }

    public function do(Input $input, Output $output): ExitCode
    {
        $project = $input->getOption('project');
        $group = $input->getOption('group');
        $includeDev = (bool) $input->getOption('dev');
        $dryRun = (bool) $input->getOption('dry-run');

        $basePath = ROOT_DIR . "/src/$project";
        if (!is_dir($basePath)) {
            Output::error("Project '$project' not found.");
            return ExitCode::FAILURE;
        }

        new ApplicationPaths($this->container)->register($project);
        $this->container->get(DatabaseConnection::class);

        if (!DatabaseConnection::isConnected()) {
            Output::error('Database not connected.');
            return ExitCode::FAILURE;
        }

        $manager = new SeedManager($this->container);

        $directory = "$basePath/Database/Seeder";
        $namespace = "Neo\\Src\\$project\\Database\\Seeder";

        $seeders = $manager->filterByGroup(
            $manager->discover($directory, $namespace),
            $group,
            $includeDev
        );

        if ($seeders === []) {
            Output::warning('No seeders found for the selected scope.');
            return ExitCode::SUCCESS;
        }

        Output::title("Seeders — $project");
        foreach ($seeders as $seeder) {
            Output::muted(sprintf('  · [%d] %s (%s)', $seeder['order'], $this->shortName($seeder['class']), $seeder['group']));
        }

        if ($dryRun) {
            Output::info('Dry run: nothing executed.');
            return ExitCode::SUCCESS;
        }

        if (!Input::confirm(count($seeders) . ' seeder(s) will run. Continue ?', true)) {
            Output::muted('Cancelled.');
            return ExitCode::SUCCESS;
        }

        foreach ($manager->run($seeders) as $class) {
            Output::success('Seeded: ' . $this->shortName($class));
        }

        return ExitCode::SUCCESS;
    }

    private function shortName(string $fqcn): string
    {
        $parts = explode('\\', $fqcn);
        return end($parts);
    }

    /**
     * @return list<string>
     */
    protected function getAvailableProjects(): array
    {
        return array_map('basename', glob(ROOT_DIR . '/src/*', GLOB_ONLYDIR) ?: []);
    }
}