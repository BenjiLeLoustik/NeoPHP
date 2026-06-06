<?php
declare(strict_types=1);

namespace Neo\Core\Database\Commands;

use Neo\Core\Console\AbstractCommand;
use Neo\Core\Console\Attribute\Command;
use Neo\Core\Console\Helper\Args;
use Neo\Core\Console\Helper\Input;
use Neo\Core\Console\Helper\Output;
use Neo\Core\Database\DatabaseConnection;
use Neo\Core\Database\DatabaseIntrospector;
use Neo\Core\Database\ORM\ORM;
use Neo\Core\DI\Container;

#[Command(
    name: 'database:generate',
    description: 'Generate Models and Repositories from the database schema',
    category: 'Database'
)]
final class DatabaseGenerateCommand extends AbstractCommand
{
    public function __construct(
        private Container $container
    ) {}

    public function execute(array $args): void
    {
        $project = Args::option($args, '--project');
        $only = Args::option($args, '--only');
        $force = Args::flag($args, '--force');

        if (!$project) {
            $projects = $this->getAvailableProjects();

            if (empty($projects)) {
                Output::error('No projects found in ./src/');
                return;
            }

            $project = Input::choice('Target project ?', $projects);
        }

        if (!$only) {
            $only = Input::choice(
                'What to generate ?',
                ['all', 'models', 'repositories', 'forms'],
                'all'
            );
        }

        $srcPath = ROOT_DIR . "/src/$project";

        if (!is_dir($srcPath)) {
            Output::error("Project '$project' not found in ./src/");
            return;
        }

        $this->container->set('application', $project);
        $this->container->set('projectPath', $srcPath);
        $this->container->set('controllerNamespace', "Neo\\Src\\$project\\App\\Controllers");
        $this->container->set('controllersPath', "$srcPath/App/Controllers");
        $this->container->set('storagePath', "$srcPath/Storage");
        $this->container->set('configsPath', "$srcPath/Config");
        $this->container->set('repositoryPath', "$srcPath/Repository");
        $this->container->set('modelPath', "$srcPath/Model");
        $this->container->set('formPath', "$srcPath/App/Forms");

        $this->container->set('modelNamespace', 'Neo\\Src\\' . $project . '\\Model');
        $this->container->set('repositoryNamespace', 'Neo\\Src\\' . $project . '\\Repository');
        $this->container->set('formNamespace', 'Neo\\Src\\' . $project . '\\App\\Forms');

        Output::newLine();
        Output::title("Generating from database schema for '$project'");
        Output::label('Target',  $only);
        Output::label('Force',   $force ? 'yes' : 'no');
        Output::newLine();

        try {
            $this->container->get(DatabaseConnection::class);

            if (!DatabaseConnection::isConnected()) {
                Output::error('Database is not connected. Check database.config.php.');
                return;
            }

            $introspector = new DatabaseIntrospector($this->container);
            $tables = $introspector->getTables();

            if (empty($tables)) {
                Output::warning('No tables found in the database.');
                return;
            }

            Output::info(count($tables) . ' table(s) found : ' . implode(', ', $tables));
            Output::newLine();

            $orm = new ORM($this->container);
            $orm->generateSelective(
                generateModels: in_array($only, ['all', 'models'], true),
                generateRepositories: in_array($only, ['all', 'repositories'], true),
                generateForms: in_array($only, ['all', 'forms'], true),
                force: $force,
            );

            Output::success("Generation completed for project '$project'.");

        } catch (\Throwable $e) {
            Output::error('Generation failed: ' . $e->getMessage());
        }
    }

    public function getHelp(): string
    {
        Output::usage('db:generate', $this->getDescription());
        Output::option('--project=<name>', 'Target project inside ./src/ (interactive selection if omitted)');
        Output::option('--only=<target>', 'What to generate: all, models, repositories, forms (default: all)');
        Output::option('--force', 'Bypass lock file and overwrite existing files');
        Output::newLine();
        echo "  Examples:\n";
        Output::example('php bin/neo db:generate --project=Blog');
        Output::example('php bin/neo db:generate --project=Blog --only=models');
        Output::example('php bin/neo db:generate --project=Blog --only=repositories --force');
        Output::example('php bin/neo db:generate');

        return '';
    }
}