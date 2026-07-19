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
use Neo\Core\Database\Connection\DatabaseConnection;
use Neo\Core\Database\Introspector\DatabaseIntrospector;
use Neo\Core\Database\ORM\Model\ModelGenerator;
use Neo\Core\Database\ORM\ORM;
use Neo\Core\DI\Container;

#[Command(
    name: 'database:generate',
    description: 'Generate Models and Repositories from the database schema',
    category: 'Database',
)]
final class DatabaseGenerateCommand extends AbstractCommand
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
            name: 'only',
            shortcut: null,
            mode: InputOption::REQUIRED,
            description: 'Target (all, models, repositories, forms)',
        );

        $this->addOption(
            name: 'force',
            shortcut: null,
            mode: InputOption::NONE,
            description: 'Overwrite files',
        );
    }

    public function do(Input $input, Output $output): ExitCode
    {
        $project = $input->getOption('project') ?? Input::choice('Target project ?', $this->getAvailableProjects());
        $only = $input->getOption('only') ?? Input::choice('What to generate ?', ['all', 'models', 'repositories', 'forms'], 'all');
        $force = (bool) $input->getOption('force');

        $srcPath = ROOT_DIR . "/src/$project";
        if (!is_dir($srcPath)) {
            Output::error("Project '$project' not found.");
            return ExitCode::FAILURE;
        }

        new ApplicationPaths($this->container)->register($project);

        Output::title("Generating for '$project'");

        try {
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

            Output::info(count($tables) . ' table(s) found.');

            $orm = new ORM($this->container);
            $orm->generateSelective(
                generateModels: in_array($only, ['all', 'models'], true),
                generateRepositories: in_array($only, ['all', 'repositories'], true),
                generateForms: in_array($only, ['all', 'forms'], true),
                force: $force,
            );


            $modelGenerator = new ModelGenerator($this->container);
            $validClassNames = array_map(fn($t) => $modelGenerator->resolveClassName($t), $tables);
            $orphans = $modelGenerator->findOrphanModels($validClassNames);

            if (!empty($orphans)) {
                Output::newLine();
                Output::warning('Orphan model files detected (table no longer exists):');
                foreach ($orphans as $orphan) {
                    Output::muted("  - Database/Model/{$orphan}.php");
                }
                Output::muted('Not deleted automatically — remove manually once you\'ve confirmed this is intentional.');
            }

            Output::success("Generation completed.");

            return ExitCode::SUCCESS;
        } catch (\Throwable $e) {
            Output::error('Generation failed: ' . $e->getMessage());
            return ExitCode::FAILURE;
        }
    }

    protected function getAvailableProjects(): array
    {
        return array_map('basename', glob(ROOT_DIR . '/src/*', GLOB_ONLYDIR));
    }
}