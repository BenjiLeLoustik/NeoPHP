<?php
declare(strict_types=1);

namespace Neo\Core\Cron\Commands;

use Neo\Core\Application\ApplicationPaths;
use Neo\Core\Console\Abstract\AbstractCommand;
use Neo\Core\Console\Attribute\Command;
use Neo\Core\Console\Enum\ExitCode;
use Neo\Core\Console\Input\Input;
use Neo\Core\Console\Input\InputOption;
use Neo\Core\Console\Output\Output;
use Neo\Core\Cron\Runner\CronRunner;
use Neo\Core\Cron\Scanner\CronScanner;
use Neo\Core\DI\Container;

#[Command(
    name: 'cron:run',
    description: 'Run all due cron jobs for a project',
    category: 'Cron',
)]
final class CronRunCommand extends AbstractCommand
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
        $project = $input->getOption('project') ?? Input::choice('Target project ?', $this->getAvailableProjects());

        if (!is_dir(ROOT_DIR . "/src/$project")) {
            Output::error("Project '$project' not found.");
            return ExitCode::FAILURE;
        }

        new ApplicationPaths($this->container)->register($project);
        $cronsPath = $this->container->get('cronsPath');

        $scanner = new CronScanner();
        $jobs = $scanner->scan($cronsPath);

        if (empty($jobs)) {
            Output::muted('No cron jobs found.');
            return ExitCode::SUCCESS;
        }

        $runner = new CronRunner($this->container);

        $runner->run($jobs);

        return ExitCode::SUCCESS;
    }

    protected function getAvailableProjects(): array
    {
        return array_map('basename', glob(ROOT_DIR . '/src/*', GLOB_ONLYDIR));
    }
}