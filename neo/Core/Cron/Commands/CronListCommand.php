<?php
declare(strict_types=1);

namespace Neo\Core\Cron\Commands;

use Neo\Core\Console\AbstractCommand;
use Neo\Core\Console\Attribute\Command;
use Neo\Core\Console\Enum\ExitCode;
use Neo\Core\Console\Input\Input;
use Neo\Core\Console\Input\InputOption;
use Neo\Core\Console\Output\Output;
use Neo\Core\Cron\CronScanner;
use Neo\Core\DI\Container;

#[Command(
    name: 'cron:list',
    description: 'List all registered cron jobs for a project',
    category: 'Cron',
)]
final class CronListCommand extends AbstractCommand
{
    public function __construct(
        private readonly Container $container
    ) {}

    public function configure(): void
    {
        $this->addOption(
            name: 'project',
            shortcut: null,
            mode: InputOption::REQUIRED,
            description: 'Target project',
        );
    }

    public function do(Input $input, Output $output): ExitCode
    {
        try {
            $cronsPath = $this->container->get('cronsPath');
        } catch (\Throwable) {
            $projects = $this->getAvailableProjects();
            if (empty($projects)) {
                Output::error('No projects found.');
                return ExitCode::FAILURE;
            }

            $project = $input->getOption('project') ?? Input::choice('Target project ?', $projects);
            $cronsPath = ROOT_DIR . "/src/$project/App/Crons";
        }

        $scanner = new CronScanner();
        $jobs = $scanner->scan($cronsPath);

        if (empty($jobs)) {
            Output::muted('No cron jobs found.');
            return ExitCode::SUCCESS;
        }

        Output::title('Registered Cron Jobs');

        foreach ($jobs as $job) {
            echo '  ' . Output::colorize(str_pad($job['expression'], 20), 'cyan')
                . Output::colorize(str_pad($job['class'] . '::' . $job['method'], 48), 'white')
                . Output::colorize($job['description'], 'dim')
                . "\n";
        }

        Output::newLine();
        return ExitCode::SUCCESS;
    }
}