<?php
declare(strict_types=1);

namespace Neo\Core\Cron\Commands;

use Neo\Core\Console\AbstractCommand;
use Neo\Core\Console\Attribute\Command;
use Neo\Core\Console\Helper\Input;
use Neo\Core\Console\Helper\Output;
use Neo\Core\Cron\CronScanner;
use Neo\Core\DI\Container;

#[Command(
    name: 'cron:list',
    description: 'List all registered cron jobs for a project',
    category: 'Cron'
)]
final class CronListCommand extends AbstractCommand
{
    public function __construct(
        private readonly Container $container
    ) {}

    public function execute(array $args): void
    {
        try {
            $cronsPath = $this->container->get('cronsPath');
        } catch (\Throwable) {
            $projects = $this->getAvailableProjects();

            if (empty($projects)) {
                Output::error('No projects found in ./src/');
                return;
            }

            Output::warning('You must pass --project=<name> to use this command.');
            $project = Input::choice('Target project ?', $projects);
            Output::muted("Re-run with: php bin/neo {$this->getName()} --project=$project");
            return;
        }

        $scanner = new CronScanner();
        $jobs = $scanner->scan($cronsPath);

        if (empty($jobs)) {
            Output::muted('No cron jobs found.');
            return;
        }

        Output::title('Registered Cron Jobs');

        foreach ($jobs as $job) {
            echo '  ' . Output::colorize(str_pad($job['expression'], 20), 'cyan')
                . Output::colorize(str_pad($job['class'] . '::' . $job['method'], 48), 'white')
                . Output::colorize($job['description'], 'dim')
                . "\n";
        }

        Output::newLine();
    }

    public function getHelp(): string
    {
        Output::usage($this->getName(), $this->getDescription());
        Output::option('--project=<name>', 'Target project inside ./src/');
        Output::newLine();
        echo "  Examples:\n";
        Output::example("php bin/neo {$this->getName()} --project=MyApp");

        return '';
    }
}