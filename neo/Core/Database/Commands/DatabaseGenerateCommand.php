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