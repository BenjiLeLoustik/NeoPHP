<?php
declare(strict_types=1);

namespace Neo\Core\Console\Commands;

use Neo\Core\Console\Attribute\Command;
use Neo\Core\Console\Helper\Output;
use Neo\Core\Console\Interface\CommandInterface;
use Neo\Core\Cron\CronRunner;
use Neo\Core\Cron\CronScanner;
use Neo\Core\DI\Container;

#[Command(
    name: 'cron:run',
    description: 'Run all due cron jobs for a project'
)]
final class CronRunCommand implements CommandInterface
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

    public function getName(): string
    {
        return 'cron:run';
    }

    public function getDescription(): string
    {
        return 'Run all due cron jobs for a project';
    }

    public function getHelp(): string
    {
        Output::usage('cron:run', $this->getDescription());
        Output::option('--project=<name>', 'Target project inside ./src/');
        Output::newLine();
        echo "  Examples:\n";
        Output::example('php bin/neo cron:run --project=MyApp');
        Output::example('* * * * * php bin/neo cron:run --project=MyApp');

        return '';
    }
}