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
use Neo\Core\Database\Migration\MigrationGenerator;
use Neo\Core\DI\Container;

#[Command(
    name: 'database:migration:generate',
    description: 'Generate a migration file from the current database schema',
    category: 'database'
)]
final class DatabaseMigrationGenerateCommand implements CommandInterface
{
    public function __construct(private Container $container) {}

    public function execute(array $args): void
    {
        $project = Args::option($args, '--project');
        $name = Args::option($args, '--name');

        if (!$project) {
            $projects = $this->getAvailableProjects();

            if (empty($projects)) {
                Output::error('No projects found in ./src/');
                return;
            }

            $project = Input::choice('Target project ?', $projects);
        }

        if (!$name) {
            $name = Input::ask('Migration name ?', 'initial_schema');
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

            $introspector = new DatabaseIntrospector($this->container);
            $tables = $introspector->getTables();

            if (empty($tables)) {
                Output::warning('No tables found in the database. Nothing to generate.');
                return;
            }

            Output::info(count($tables) . ' table(s) found: ' . implode(', ', $tables));
            Output::newLine();

            $generator = new MigrationGenerator($introspector);
            $file = $generator->generate($migrationsPath, $name);

            Output::success('Migration file generated:');
            Output::muted('  ' . str_replace(ROOT_DIR, '', $file));

        } catch (\Throwable $e) {
            Output::error('Generation failed: ' . $e->getMessage());
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
        return 'database:migration:generate';
    }

    public function getDescription(): string
    {
        return 'Generate a migration file from the current database schema';
    }

    public function getHelp(): string
    {
        Output::usage('migrate:generate', $this->getDescription());
        Output::option('--project=<name>', 'Target project inside ./src/ (interactive selection if omitted)');
        Output::option('--name=<name>', 'Migration name slug (default: initial_schema)');
        Output::newLine();
        echo "  Generated files:\n";
        Output::muted('    src/<project>/Database/Migrations/MigrationVersion_<timestamp>.php');
        Output::newLine();
        echo "  Examples:\n";
        Output::example('php bin/neo migrate:generate --project=Blog');
        Output::example('php bin/neo migrate:generate --project=Blog --name=add_users_table');
        Output::example('php bin/neo migrate:generate');

        return '';
    }
}