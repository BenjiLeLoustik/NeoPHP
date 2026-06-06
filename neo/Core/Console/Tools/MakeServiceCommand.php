<?php
declare(strict_types=1);

namespace Neo\Core\Console\Tools;

use Neo\Core\Console\AbstractCommand;
use Neo\Core\Console\Attribute\Command;
use Neo\Core\Console\Helper\Args;
use Neo\Core\Console\Helper\Fs;
use Neo\Core\Console\Helper\Input;
use Neo\Core\Console\Helper\Output;

#[Command(
    name: 'app:make:service',
    description: 'Create a Service class for a project',
    category: 'Service'
)]
final class MakeServiceCommand extends AbstractCommand
{
    public function execute(array $args): void
    {
        $service = Args::positional($args, 0);
        $project = Args::option($args, '--project');
        $directory = Args::option($args, '-d') ?? Args::option($args, '--dir');
        $force = Args::flag($args, '--force');

        if (!$service) {
            $service = Input::ask('Service name ?');
            if (!$service) {
                Output::error('Service name is required.');
                return;
            }
        }

        if (!$project) {
            $projects = $this->getAvailableProjects();

            if (empty($projects)) {
                Output::error('No projects found in ./src/');
                return;
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
                return;
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
    // TODO: add service logic
}
PHP;

        file_put_contents($path, $content);
        Output::success("Service '$service' generated for project '$project'.");
    }

    private function normalizeServiceName(string $input): string
    {
        $input = preg_replace('/[^a-zA-Z0-9]+/', ' ', $input);
        $input = str_replace(' ', '', ucwords($input));

        if (!str_ends_with($input, 'Service')) {
            $input .= 'Service';
        }

        return $input;
    }

    public function getHelp(): string
    {
        Output::usage('app:make:service', $this->getDescription());
        Output::option('<ServiceName>', '"Service" suffix added automatically');
        Output::option('--project=<name>', 'Target project inside ./src/ (interactive selection if omitted)');
        Output::option('-d, --dir <directory>', 'Create inside a sub-folder (e.g. Utils)');
        Output::option('--force', 'Overwrite existing file');
        Output::newLine();
        echo "  Examples:\n";
        Output::example('php bin/neo app:make:service Mail --project=NeoAdmin');
        Output::example('php bin/neo app:make:service Mail -d Utils --project=NeoAdmin');
        Output::example('php bin/neo app:make:service');

        return '';
    }
}