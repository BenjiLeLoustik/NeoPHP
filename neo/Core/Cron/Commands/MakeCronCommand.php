<?php
declare(strict_types=1);

namespace Neo\Core\Cron\Commands;

use Neo\Core\Console\Abstract\AbstractCommand;
use Neo\Core\Console\Attribute\Command;
use Neo\Core\Console\Enum\ExitCode;
use Neo\Core\Console\Helper\Fs;
use Neo\Core\Console\Input\Input;
use Neo\Core\Console\Input\InputArgument;
use Neo\Core\Console\Input\InputOption;
use Neo\Core\Console\Output\Output;

#[Command(
    name: 'make:cron',
    description: 'Create a Cron class for a project',
    category: 'Cron',
)]
final class MakeCronCommand extends AbstractCommand
{
    private const array COMMON_EXPRESSIONS = [
        '* * * * *',
        '0 * * * *',
        '0 0 * * *',
        '0 0 * * 0',
        '0 0 1 * *',
    ];

    public function configure(): void
    {
        $this->addArgument(
            name: 'cron',
            description: 'Cron class name',
            mode: InputArgument::OPTIONAL,
        );

        $this->addOption(
            name: 'project',
            shortcut: null,
            mode: InputOption::REQUIRED,
            description: 'Target project',
        );

        $this->addOption(
            name: 'expression',
            shortcut: null,
            mode: InputOption::REQUIRED,
            description: 'Cron expression',
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
        $cron = $input->getArgument('cron') ?? Input::ask('Cron name ?');
        if (!$cron) return ExitCode::INVALID;

        $project = $input->getOption('project') ?? Input::choice('Target project ?', $this->getAvailableProjects());
        $expression = $input->getOption('expression') ?? Input::autocomplete('Cron expression ?', self::COMMON_EXPRESSIONS, '* * * * *');
        $force = (bool) $input->getOption('force');

        $cron = $this->normalizeCronName($cron);
        $basePath = ROOT_DIR . "/src/$project/App/Crons";

        Fs::ensureDir($basePath);
        $path = "$basePath/$cron.php";

        if (file_exists($path) && !$force) {
            if (!Input::confirm("Cron '$cron' already exists. Overwrite ?", false)) {
                Output::muted('Cancelled.');
                return ExitCode::SUCCESS;
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
        Output::success("Cron '$cron' generated.");

        return ExitCode::SUCCESS;
    }

    private function normalizeCronName(string $input): string
    {
        $input = str_replace(' ', '', ucwords(preg_replace('/[^a-zA-Z0-9]+/', ' ', $input)));
        return str_ends_with($input, 'Cron') ? $input : $input . 'Cron';
    }

    protected function getAvailableProjects(): array
    {
        return array_map('basename', glob(ROOT_DIR . '/src/*', GLOB_ONLYDIR));
    }
}