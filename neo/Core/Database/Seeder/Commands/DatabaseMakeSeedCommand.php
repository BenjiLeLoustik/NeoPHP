<?php
declare(strict_types=1);

namespace Neo\Core\Database\Seeder\Commands;

use Neo\Core\Console\Abstract\AbstractCommand;
use Neo\Core\Console\Attribute\Command;
use Neo\Core\Console\Enum\ExitCode;
use Neo\Core\Console\Helper\Fs;
use Neo\Core\Console\Input\Input;
use Neo\Core\Console\Input\InputArgument;
use Neo\Core\Console\Input\InputOption;
use Neo\Core\Console\Output\Output;

#[Command(
    name: 'database:make:seed',
    description: 'Create a seeder class for a project',
    category: 'Database',
)]
final class DatabaseMakeSeedCommand extends AbstractCommand
{
    public function configure(): void
    {
        $this->addArgument(
            name: 'name',
            description: 'Seeder name',
            mode: InputArgument::OPTIONAL,
        );

        $this->addOption(
            name: 'project',
            shortcut: null,
            mode: InputOption::REQUIRED,
            description: 'Target project',
        );

        $this->addOption(
            name: 'order',
            shortcut: null,
            mode: InputOption::OPTIONAL,
            description: 'Execution order',
            default: '0',
        );

        $this->addOption(
            name: 'group',
            shortcut: null,
            mode: InputOption::OPTIONAL,
            description: 'Seeder group (reference or demo)',
            default: 'reference',
        );

        $this->addOption(
            name: 'force',
            shortcut: null,
            mode: InputOption::NONE,
            description: 'Overwrite the file if it exists',
        );
    }

    public function do(Input $input, Output $output): ExitCode
    {
        $name = $input->getArgument('name') ?? Input::ask('Seeder name ?');
        if (!$name) {
            Output::error('Seeder name is required.');
            return ExitCode::INVALID;
        }

        $project = $input->getOption('project');
        $order = (int) $input->getOption('order');
        $group = $input->getOption('group');
        $force = (bool) $input->getOption('force');

        $name = Fs::pascalCase($name);
        if (!str_ends_with($name, 'Seeder')) {
            $name .= 'Seeder';
        }

        $basePath = ROOT_DIR . "/src/$project";
        if (!is_dir($basePath)) {
            Output::error("Project '$project' not found.");
            return ExitCode::FAILURE;
        }

        $seederDir = "$basePath/Database/Seeder";
        Fs::ensureDir($seederDir);
        $path = "$seederDir/$name.php";

        if (file_exists($path) && !$force) {
            if (!Input::confirm("Seeder '$name' exists. Overwrite ?", false)) {
                Output::warning('Aborted.');
                return ExitCode::SUCCESS;
            }
        }

        $namespace = "Neo\\Src\\$project\\Database\\Seeder";
        file_put_contents($path, $this->render($namespace, $name, $order, $group));

        Output::success("Seeder '$name' generated at $path");

        return ExitCode::SUCCESS;
    }

    private function render(string $namespace, string $name, int $order, string $group): string
    {
        return <<<PHP
<?php
declare(strict_types=1);

namespace $namespace;

use Neo\\Core\\Database\\ORM\\Persistence\\EntityManager;
use Neo\\Core\\Database\\Seeder\\Attribute\\Seeder;
use Neo\\Core\\Database\\Seeder\\Interface\\SeedInterface;

#[Seeder(order: $order, group: '$group')]
final class $name implements SeedInterface
{
    public function run(EntityManager \$entityManager): void
    {

    }
}

PHP;
    }

    /**
     * @return list<string>
     */
    protected function getAvailableProjects(): array
    {
        return array_map('basename', glob(ROOT_DIR . '/src/*', GLOB_ONLYDIR) ?: []);
    }
}