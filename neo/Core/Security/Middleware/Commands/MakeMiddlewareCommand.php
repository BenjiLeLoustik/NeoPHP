<?php
declare(strict_types=1);

namespace Neo\Core\Security\Middleware\Commands;

use Neo\Core\Console\Attribute\Command;
use Neo\Core\Console\Helper\Args;
use Neo\Core\Console\Helper\Fs;
use Neo\Core\Console\Helper\Output;
use Neo\Core\Console\Interface\CommandInterface;

#[Command(
    name: 'make:middleware',
    description: 'Create a Middleware for a project'
)]
final class MakeMiddlewareCommand implements CommandInterface
{
    public function execute(array $args): void
    {
        $middleware = Args::positional($args, 0);
        $project = Args::option($args, '--project');
        $directory = Args::option($args, '-d') ?? Args::option($args, '--dir');
        $force = Args::flag($args, '--force');

        if (!$middleware || !$project) {
            Output::error('Missing arguments.');
            Output::muted('Usage: php bin/neo make:middleware <MiddlewareName> --project=<name>');
            return;
        }

        $middleware = $this->normalizeMiddlewareName($middleware);
        $directory = $directory ? Fs::normalizeDir($directory) : null;

        $basePath = ROOT_DIR . "/src/$project/App/Middlewares";

        if ($directory) {
            $basePath .= "/$directory";
        }

        Fs::ensureDir($basePath);

        $path = "$basePath/$middleware.php";

        if (file_exists($path) && !$force) {
            Output::warning("Middleware already exists. Use --force to overwrite.");
            return;
        }

        $namespace = "Neo\\Src\\$project\\App\\Middlewares";

        if ($directory) {
            $namespace .= '\\' . str_replace('/', '\\', $directory);
        }

        $content = <<<PHP
<?php
declare(strict_types=1);

namespace $namespace;

use Neo\Core\Security\Middleware\Interface\MiddlewareInterface;

final class $middleware implements MiddlewareInterface
{
    public function handle(): bool
    {
        // TODO: implement middleware logic
        return false;
    }
}
PHP;

        file_put_contents($path, $content);
        Output::success("Middleware '$middleware' generated for project '$project'.");
    }

    private function normalizeMiddlewareName(string $input): string
    {
        $input = preg_replace('/[^a-zA-Z0-9]+/', ' ', $input);
        $input = str_replace(' ', '', ucwords($input));

        if (!str_ends_with($input, 'Middleware')) {
            $input .= 'Middleware';
        }

        return $input;
    }

    public function getName(): string
    {
        return 'make:middleware';
    }

    public function getDescription(): string
    {
        return 'Create a Middleware for a project';
    }

    public function getHelp(): string
    {
        Output::usage('make:middleware', $this->getDescription());
        Output::option('<MiddlewareName>',      '"Middleware" suffix added automatically');
        Output::option('--project=<name>',      'Target project inside ./src/');
        Output::option('-d, --dir <directory>', 'Create inside a sub-folder (e.g. Security)');
        Output::option('--force',               'Overwrite existing file');
        Output::newLine();
        echo "  Examples:\n";
        Output::example('php bin/neo make:middleware Auth --project=NeoAdmin');
        Output::example('php bin/neo make:middleware Auth -d Security --project=NeoAdmin');

        return '';
    }
}