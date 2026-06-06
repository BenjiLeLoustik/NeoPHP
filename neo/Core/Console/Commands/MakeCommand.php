<?php

namespace Neo\Core\Console\Commands;

use Neo\Core\Console\AbstractCommand;
use Neo\Core\Console\Attribute\Command;
use Neo\Core\Console\Helper\Args;
use Neo\Core\Console\Helper\Fs;
use Neo\Core\Console\Helper\Input;
use Neo\Core\Console\Helper\Output;

#[Command(
    name: 'app:make:command',
    description: 'Create a new CLI Command for a project',
    category: 'Command'
)]
class MakeCommand extends AbstractCommand
{

    private const CATEGORIES = ['app', 'other', 'testing', 'cron', 'config', 'debug'];

    public function execute(array $args): void
    {
        $commandName = Args::positional($args, 0);
        $project = Args::option($args, '--project');
        $cmdName = Args::option($args, '--name');
        $category = Args::option($args, '--category');
        $force = Args::flag($args, '--force');

        if (!$commandName) {
            $commandName = Input::ask('Command class name ? (e.g. CleanLogsCommand)');
            if (!$commandName) {
                Output::error('Command class name is required.');
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

        if (!$cmdName) {
            $guessed = $this->guessCommandName($commandName);
            $cmdName = Input::ask('Command name ? (e.g. cache:clear)', $guessed);
        }

        if (!$category) {
            $selected = Input::choice('Category ?', self::CATEGORIES, 'other');

            if ($selected === 'other') {
                $category = Input::ask('Custom category name ?', 'other');
            } else {
                $category = $selected;
            }
        }

        $commandName = $this->normalizeCommandName($commandName);
        $basePath = ROOT_DIR . "/src/$project/App/Commands";

        Fs::ensureDir($basePath);

        $path = "$basePath/$commandName.php";

        if (file_exists($path) && !$force) {
            if (!Input::confirm("Command '$commandName' already exists. Overwrite ?", false)) {
                Output::muted('Cancelled.');
                return;
            }
        }

        $namespace = "Neo\\Src\\$project\\App\\Commands";
        $content = <<<PHP
<?php
declare(strict_types=1);

namespace $namespace;

use Neo\Core\Console\AbstractCommand;
use Neo\Core\Console\Attribute\Command;
use Neo\Core\Console\Helper\Args;
use Neo\Core\Console\Helper\Input;
use Neo\Core\Console\Helper\Output;

#[Command(
    name: '$cmdName',
    description: 'Add a short description',
    category: '$category'
)]
final class $commandName extends AbstractCommand
{
    public function execute(array \$args): void
    {
        // TODO: implement command logic

        // Retrieve a named option:     \$value = Args::option(\$args, '--option');
        // Retrieve a positional arg:   \$value = Args::positional(\$args, 0);
        // Retrieve a boolean flag:     \$flag  = Args::flag(\$args, '--flag');

        // Interactive input:
        // \$answer   = Input::ask('Question ?', 'default');
        // \$confirmed = Input::confirm('Are you sure ?', false);
        // \$choice   = Input::choice('Pick one ?', ['a', 'b', 'c']);
        // \$secret   = Input::secret('Password ?');

        Output::success('Done.');
    }

    public function getHelp(): string
    {
        Output::usage('$cmdName', \$this->getDescription());
        Output::option('--option=<value>', 'TODO: describe option');
        Output::newLine();
        echo "  Examples:\n";
        Output::example('php bin/neo $cmdName');

        return '';
    }
}
PHP;

        file_put_contents($path, $content);

        Output::newLine();
        Output::success("Command '$commandName' generated for project '$project'.");
        Output::label('Class', "$namespace\\$commandName");
        Output::label('Command', $cmdName);
        Output::label('Category', $category);
        Output::label('File', "src/$project/App/Commands/$commandName.php");
        Output::newLine();
    }

    private function normalizeCommandName(string $input): string
    {
        $input = preg_replace('/[^a-zA-Z0-9]+/', ' ', $input);
        $input = str_replace(' ', '', ucwords($input));

        if (!str_ends_with($input, 'Command')) {
            $input .= 'Command';
        }

        return $input;
    }

    private function guessCommandName(string $className): string
    {
        $name = preg_replace('/Command$/', '', $className);
        $name = preg_replace('/([A-Z])/', ':$1', lcfirst($name));
        return strtolower(ltrim($name, ':'));
    }

    public function getHelp(): string
    {
        Output::usage('make:command', $this->getDescription());
        Output::option('<CommandName>', 'Command class name — "Command" suffix added automatically');
        Output::option('--project=<name>', 'Target project inside ./src/ (interactive selection if omitted)');
        Output::option('--cmd=<name>', 'CLI command name (e.g. cache:clear) — guessed from class name if omitted');
        Output::option('--category=<cat>', 'Category for help grouping (interactive selection if omitted)');
        Output::option('--force', 'Overwrite existing file');
        Output::newLine();
        echo "  Examples:\n";
        Output::example('php bin/neo make:command CleanLogs --project=Blog');
        Output::example('php bin/neo make:command CleanLogs --cmd=logs:clean --category=cache --project=Blog');
        Output::example('php bin/neo make:command');

        return '';
    }
}