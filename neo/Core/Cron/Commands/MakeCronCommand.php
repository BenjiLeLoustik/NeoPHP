<?php
declare(strict_types=1);

namespace Neo\Core\Cron\Commands;

use Neo\Core\Console\Attribute\Command;
use Neo\Core\Console\Helper\Args;
use Neo\Core\Console\Helper\Fs;
use Neo\Core\Console\Helper\Output;
use Neo\Core\Console\Interface\CommandInterface;

#[Command(
    name: 'make:cron',
    description: 'Create a Cron class for a project'
)]
final class MakeCronCommand implements CommandInterface
{
    public function execute(array $args): void
    {
        $cron = Args::positional($args, 0);
        $project = Args::option($args, '--project');
        $expression = Args::option($args, '--expression') ?? '* * * * *';
        $force = Args::flag($args, '--force');

        if (!$cron || !$project) {
            Output::error('Missing arguments.');
            Output::muted('Usage: php bin/neo make:cron <CronName> --project=<name>');
            return;
        }

        $cron = $this->normalizeCronName($cron);
        $basePath = ROOT_DIR . "/src/$project/App/Crons";

        Fs::ensureDir($basePath);

        $path = "$basePath/$cron.php";

        if (file_exists($path) && !$force) {
            Output::warning("Cron already exists. Use --force to overwrite.");
            return;
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

    public function getName(): string
    {
        return 'make:cron';
    }

    public function getDescription(): string
    {
        return 'Create a Cron class for a project';
    }

    public function getHelp(): string
    {
        Output::usage('make:cron', $this->getDescription());
        Output::option('<CronName>',              '"Cron" suffix added automatically');
        Output::option('--project=<name>',        'Target project inside ./src/');
        Output::option('--expression=<expr>',     'Cron expression (default: "* * * * *")');
        Output::option('--force',                 'Overwrite existing file');
        Output::newLine();
        echo "  Examples:\n";
        Output::example('php bin/neo make:cron CleanLogs --project=MyApp');
        Output::example('php bin/neo make:cron CleanLogs --expression="0 0 * * *" --project=MyApp');

        return '';
    }
}