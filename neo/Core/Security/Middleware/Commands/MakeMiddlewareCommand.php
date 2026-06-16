<?php
declare(strict_types=1);

namespace Neo\Core\Security\Middleware\Commands;

use Neo\Core\Console\AbstractCommand;
use Neo\Core\Console\Attribute\Command;
use Neo\Core\Console\Enum\ExitCode;
use Neo\Core\Console\Helper\Fs;
use Neo\Core\Console\Input\Input;
use Neo\Core\Console\Input\InputArgument;
use Neo\Core\Console\Input\InputOption;
use Neo\Core\Console\Output\Output;

#[Command(
    name: 'make:middleware',
    description: 'Create a Middleware for a project',
    category: 'Middleware',
)]
final class MakeMiddlewareCommand extends AbstractCommand
{
    public function configure(): void
    {
        $this->addArgument(
            name: 'middleware',
            description: 'Middleware class name',
            mode: InputArgument::OPTIONAL,
        );

        $this->addOption(
            name: 'project',
            shortcut: null,
            mode: InputOption::REQUIRED,
            description: 'Target project',
        );

        $this->addOption(
            name: 'dir',
            shortcut: 'd',
            mode: InputOption::REQUIRED,
            description: 'Sub-folder',
        );

        $this->addOption(
            name: 'force',
            shortcut: null,
            mode: InputOption::NONE,
            description: 'Overwrite file',
        );
    }

    public function do(Input $input, Output $output): ExitCode
    {
        $middleware = $input->getArgument('middleware') ?? Input::ask('Middleware name ?', 'Auth');
        if (!$middleware) return ExitCode::INVALID;

        $project = $input->getOption('project') ?? Input::choice('Target project ?', $this->getAvailableProjects());
        $directory = $input->getOption('dir');
        $force = (bool) $input->getOption('force');

        $middleware = $this->normalizeMiddlewareName($middleware);
        $directory = $directory !== '' ? Fs::normalizeDir($directory) : null;

        $basePath = ROOT_DIR . "/src/$project/App/Middlewares";
        $namespace = "Neo\\Src\\$project\\App\\Middlewares";

        if ($directory) {
            $basePath .= "/$directory";
            $namespace .= '\\' . str_replace('/', '\\', $directory);
        }

        Fs::ensureDir($basePath);
        $path = "$basePath/$middleware.php";

        if (file_exists($path) && !$force) {
            Output::warning("Middleware already exists.");
            return ExitCode::FAILURE;
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
        Output::success("Middleware '$middleware' generated.");

        return ExitCode::SUCCESS;
    }

    private function normalizeMiddlewareName(string $input): string
    {
        $input = str_replace(' ', '', ucwords(preg_replace('/[^a-zA-Z0-9]+/', ' ', $input)));
        return str_ends_with($input, 'Middleware') ? $input : $input . 'Middleware';
    }

    protected function getAvailableProjects(): array
    {
        return array_map('basename', glob(ROOT_DIR . '/src/*', GLOB_ONLYDIR));
    }
}