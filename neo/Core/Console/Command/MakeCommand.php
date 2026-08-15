<?php
declare(strict_types=1);

namespace Neo\Core\Console\Command;

use Neo\Core\Console\Abstract\AbstractCommand;
use Neo\Core\Console\Attribute\Command;
use Neo\Core\Console\Enum\ExitCode;
use Neo\Core\Console\Helper\Fs;
use Neo\Core\Console\Input\Input;
use Neo\Core\Console\Input\InputArgument;
use Neo\Core\Console\Input\InputOption;
use Neo\Core\Console\Output\Output;

#[Command(
    name: 'app:make:command',
    description: 'Create a new CLI Command for a project',
    category: 'Command'
)]
final class MakeCommand extends AbstractCommand
{
    private const CATEGORIES = ['app', 'other', 'testing', 'cron', 'config', 'debug'];

    public function configure(): void
    {
        $this->addArgument(
            name: 'commandName',
            description: 'Command class name (e.g. CleanLogsCommand)',
            mode: InputArgument::OPTIONAL
        );

        $this->addOption(
            name: 'project',
            mode: InputOption::REQUIRED,
            description: 'Target project inside ./src/'
        );

        $this->addOption(
            name: 'name',
            mode: InputOption::REQUIRED,
            description: 'CLI command name (e.g. cache:clear)'
        );

        $this->addOption(
            name: 'category',
            mode: InputOption::REQUIRED,
            description: 'Category for help grouping'
        );

        $this->addOption(
            name: 'force',
            mode: InputOption::NONE,
            description: 'Overwrite existing file'
        );
    }

    public function do(Input $input, Output $output): ExitCode
    {
        $commandName = $input->getArgument('commandName');
        $project = $input->getOption('project');
        $cmdName = $input->getOption('name');
        $category = $input->getOption('category');
        $force = (bool) $input->getOption('force');

        if (!$commandName) {
            $commandName = Input::ask('Command class name ? (e.g. CleanLogsCommand)');
            if (!$commandName) {
                Output::error('Command class name is required.');
                return ExitCode::INVALID;
            }
        }

        if (!$cmdName) {
            $guessed = $this->guessCommandName($commandName);
            $cmdName = Input::ask('Command name ? (e.g. cache:clear)', $guessed);
        }

        if (!$category) {
            $selected = Input::choice('Category ?', self::CATEGORIES, 'other');
            $category = ($selected === 'other')
                ? Input::ask('Custom category name ?', 'other')
                : $selected;
        }

        $commandName = $this->normalizeCommandName($commandName);
        $basePath = ROOT_DIR . "/src/$project/App/Command";

        Fs::ensureDir($basePath);
        $path = "$basePath/$commandName.php";

        if (file_exists($path) && !$force) {
            if (!Input::confirm("Command '$commandName' already exists. Overwrite ?", false)) {
                Output::muted('Cancelled.');
                return ExitCode::SUCCESS;
            }
        }

        $namespace = "Neo\\Src\\$project\\App\\Command";
        $content = <<<PHP
<?php
declare(strict_types=1);

namespace $namespace;

use Neo\Core\Console\Abstract\AbstractCommand;
use Neo\Core\Console\Attribute\Command;
use Neo\Core\Console\Enum\ExitCode;
use Neo\Core\Console\Output\Output;
use Neo\Core\Console\Input\Input;

#[Command(
    name: '$cmdName',
    description: 'Add a short description',
    category: '$category'
)]
final class $commandName extends AbstractCommand
{
    public function configure(): void
    {
        // TODO: Configure arguments and options
    }

    public function do(Input \$input, Output \$output): ExitCode
    {
        // TODO: implement command logic
        Output::success('Done.');
        return ExitCode::SUCCESS;
    }
}
PHP;

        file_put_contents($path, $content);

        Output::success("Command '$commandName' generated for project '$project'.");
        Output::info("Class: $namespace\\$commandName");
        Output::info("File: src/$project/App/Commands/$commandName.php");

        return ExitCode::SUCCESS;
    }

    private function normalizeCommandName(string $input): string
    {
        $input = $input
                |> (fn (string $s): string => preg_replace('/[^a-zA-Z0-9]+/', ' ', $s) ?? '')
                |> ucwords(...)
                |> (fn (string $s): string => str_replace(' ', '', $s));

        return str_ends_with($input, 'Command') ? $input : $input . 'Command';
    }

    private function guessCommandName(string $className): string
    {
        return $className
                |> (fn (string $s): string => preg_replace('/Command$/', '', $s) ?? '')
                |> lcfirst(...)
                |> (fn (string $s): string => preg_replace('/([A-Z])/', ':$1', $s) ?? '')
                |> (fn (string $s): string => ltrim($s, ':'))
                |> strtolower(...);
    }
}