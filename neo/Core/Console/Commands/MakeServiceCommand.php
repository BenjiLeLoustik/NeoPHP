<?php
declare(strict_types=1);

namespace Neo\Core\Console\Commands;

use Neo\Core\Console\AbstractCommand;
use Neo\Core\Console\Attribute\Command;
use Neo\Core\Console\Enum\ExitCode;
use Neo\Core\Console\Helper\Fs;
use Neo\Core\Console\Input\Input;
use Neo\Core\Console\Input\InputArgument;
use Neo\Core\Console\Input\InputOption;
use Neo\Core\Console\Output\Output;

#[Command(
    name: 'app:make:service',
    description: 'Create a Service class for a project',
    category: 'Service'
)]
final class MakeServiceCommand extends AbstractCommand
{
    public function configure(): void
    {
        $this->addArgument(
            name: 'service',
            description: 'Service name',
            mode: InputArgument::OPTIONAL
        );

        $this->addOption(
            name: 'project',
            mode: InputOption::REQUIRED,
            description: 'Target project inside ./src/'
        );

        $this->addOption(
            name: 'dir',
            shortcut: 'd',
            mode: InputOption::REQUIRED,
            description: 'Create inside a sub-folder (e.g. Utils)'
        );

        $this->addOption(
            name: 'force',
            mode: InputOption::NONE,
            description: 'Overwrite existing file'
        );
    }

    public function do(Input $input, Output $output): ExitCode
    {
        $service = $input->getArgument('service');
        $project = $input->getOption('project');
        $directory = $input->getOption('dir');
        $force = (bool) $input->getOption('force');

        if (!$service) {
            $service = Input::ask('Service name ?');
            if (!$service) {
                Output::error('Service name is required.');
                return ExitCode::INVALID;
            }
        }

        if (!$project) {
            $projects = $this->getAvailableProjects();

            if (empty($projects)) {
                Output::error('No projects found in ./src/');
                return ExitCode::FAILURE;
            }

            $project = Input::choice('Target project ?', $projects);
        }

        if (!$directory) {
            $raw = Input::ask('Sub-folder ? (leave empty to skip)', '');
            $directory = $raw !== '' ? $raw : null;
        }

        $service = $this->normalizeServiceName($service);
        $directory = $directory ? Fs::normalizeDir($directory) : null;
        $basePath = ROOT_DIR . "/src/$project/App/Services";

        if ($directory) {
            $basePath .= "/$directory";
        }

        Fs::ensureDir($basePath);
        $path = "$basePath/$service.php";

        if (file_exists($path) && !$force) {
            if (!Input::confirm("Service '$service' already exists. Overwrite ?", false)) {
                Output::muted('Cancelled.');
                return ExitCode::SUCCESS;
            }
        }

        $namespace = "Neo\\Src\\$project\\App\\Services";
        if ($directory) {
            $namespace .= '\\' . str_replace('/', '\\', $directory);
        }

        $content = <<<PHP
<?php
declare(strict_types=1);

namespace $namespace;

final class $service
{
    public function __construct()
    {
        // TODO: inject dependencies
    }
}
PHP;

        file_put_contents($path, $content);
        Output::success("Service '$service' generated for project '$project'.");

        return ExitCode::SUCCESS;
    }

    private function normalizeServiceName(string $input): string
    {
        $input = preg_replace('/[^a-zA-Z0-9]+/', ' ', $input);
        $input = str_replace(' ', '', ucwords($input));

        return str_ends_with($input, 'Service') ? $input : $input . 'Service';
    }
}