<?php
declare(strict_types=1);

namespace Neo\Core\Console\Commands;

use Neo\Core\Console\Interface\CommandInterface;
use Neo\Core\Console\Attribute\Command;
use Neo\Core\Console\Helper\Args;
use Neo\Core\Console\Helper\Fs;
use Neo\Core\Console\Helper\Output;

#[Command(
    name: 'make:service',
    description: 'Create a Service class for a project'
)]
final class MakeServiceCommand implements CommandInterface
{
    public function execute(array $args): void
    {
        $service = Args::positional($args, 0);
        $project = Args::option($args, '--project');
        $directory = Args::option($args, '-d') ?? Args::option($args, '--dir');
        $force = Args::flag($args, '--force');

        if (!$service || !$project) {
            Output::error('Missing arguments.');
            Output::muted('Usage: php bin/neo make:service <ServiceName> --project=<name>');
            return;
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
            Output::warning("Service already exists. Use --force to overwrite.");
            return;
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

    public function getName(): string
    {
        return 'make:service';
    }

    public function getDescription(): string
    {
        return 'Create a Service class for a project';
    }

    public function getHelp(): string
    {
        Output::usage('make:service', $this->getDescription());
        Output::option('<ServiceName>',         '"Service" suffix added automatically');
        Output::option('--project=<name>',      'Target project inside ./src/');
        Output::option('-d, --dir <directory>', 'Create inside a sub-folder (e.g. Utils)');
        Output::option('--force',               'Overwrite existing file');
        Output::newLine();
        echo "  Examples:\n";
        Output::example('php bin/neo make:service Mail --project=NeoAdmin');
        Output::example('php bin/neo make:service Mail -d Utils --project=NeoAdmin');

        return '';
    }
}