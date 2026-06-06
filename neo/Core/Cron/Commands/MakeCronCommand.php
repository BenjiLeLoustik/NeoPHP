<?php
declare(strict_types=1);

namespace Neo\Core\Cron\Commands;

use Neo\Core\Console\AbstractCommand;
use Neo\Core\Console\Attribute\Command;
use Neo\Core\Console\Helper\Args;
use Neo\Core\Console\Helper\Fs;
use Neo\Core\Console\Helper\Input;
use Neo\Core\Console\Helper\Output;

#[Command(
    name: 'make:cron',
    description: 'Create a Cron class for a project',
    category: 'Cron'
)]
final class MakeCronCommand extends AbstractCommand
{
    private const COMMON_EXPRESSIONS = [
        '* * * * *',
        '0 * * * *',
        '0 0 * * *',
        '0 0 * * 0',
        '0 0 1 * *',
    ];

    public function execute(array $args): void
    {
        $cron = Args::positional($args, 0);
        $project = Args::option($args, '--project');
        $expression = Args::option($args, '--expression');
        $force = Args::flag($args, '--force');

        if (!$cron) {
            $cron = Input::ask('Cron name ?');
            if (!$cron) {
                Output::error('Cron name is required.');
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

        if (!$expression) {
            $expression = Input::autocomplete(
                'Cron expression ?',
                self::COMMON_EXPRESSIONS,
                '* * * * *'
            );
        }

        $cron = $this->normalizeCronName($cron);
        $basePath = ROOT_DIR . "/src/$project/App/Crons";

        Fs::ensureDir($basePath);

        $path = "$basePath/$cron.php";

        if (file_exists($path) && !$force) {
            if (!Input::confirm("Cron '$cron' already exists. Overwrite ?", false)) {
                Output::muted('Cancelled.');
                return;
            }
        }

        $namespace = "Neo\\Src\\$project\\App\\Crons";

        $content = <<<PHP
<?php
declare(strict_types=1);

namespace $namespace;

use Neo\Core\Cron\Attribute\Cron;

final class $cron
{
    #[Cron(
        expression: '$expression',
        description: 'TODO: describe this cron job',
    )]
    public function handle(): void
    {
        // TODO: implement cron logic
    }
}
PHP;

        file_put_contents($path, $content);
        Output::success("Cron '$cron' generated for project '$project'.");
    }

    private function normalizeCronName(string $input): string
    {
        $input = preg_replace('/[^a-zA-Z0-9]+/', ' ', $input);
        $input = str_replace(' ', '', ucwords($input));

        if (!str_ends_with($input, 'Cron')) {
            $input .= 'Cron';
        }

        return $input;
    }

    public function getHelp(): string
    {
        Output::usage('make:cron', $this->getDescription());
        Output::option('<CronName>', '"Cron" suffix added automatically');
        Output::option('--project=<name>', 'Target project inside ./src/ (interactive selection if omitted)');
        Output::option('--expression=<expr>', 'Cron expression (interactive autocomplete if omitted)');
        Output::option('--force', 'Overwrite existing file');
        Output::newLine();
        echo "  Examples:\n";
        Output::example('php bin/neo make:cron CleanLogs --project=MyApp');
        Output::example('php bin/neo make:cron CleanLogs --expression="0 0 * * *" --project=MyApp');
        Output::example('php bin/neo make:cron');

        return '';
    }
}