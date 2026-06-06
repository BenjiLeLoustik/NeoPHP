<?php
declare(strict_types=1);

namespace Neo\Core\Cron\Commands;

use Neo\Core\Console\AbstractCommand;
use Neo\Core\Console\Attribute\Command;
use Neo\Core\Console\Helper\Output;
use Neo\Core\Cron\CronRunner;
use Neo\Core\Cron\CronScanner;
use Neo\Core\DI\Container;

#[Command(
    name: 'cron:run',
    description: 'Run all due cron jobs for a project',
    category: 'Cron'
)]
final class CronRunCommand extends AbstractCommand
{
    public function __construct(
        private Container $container
    ) {}

    public function execute(array $args): void
    {
        try {
            $cronsPath = $this->container->get('cronsPath');
        } catch (\Throwable) {
            Output::error('You must pass --project=<name> to use this command.');
            Output::muted("Example: php bin/neo {$this->getName()} --project=MyApp");
            return;
        }

        $scanner = new CronScanner();
        $jobs = $scanner->scan($cronsPath);

        if (empty($jobs)) {
            Output::muted('No cron jobs found.');
            return;
        }

        $runner = new CronRunner($this->container);
        $runner->run($jobs);
    }

    public function getHelp(): string
    {
        Output::usage($this->getName(), $this->getDescription());
        Output::option('--project=<name>', 'Target project inside ./src/');
        Output::newLine();
        echo "  Examples:\n";
        Output::example("php bin/neo {$this->getName()} --project=MyApp");
        Output::example("* * * * * php bin/neo {$this->getName()} --project=MyApp");

        return '';
    }
}