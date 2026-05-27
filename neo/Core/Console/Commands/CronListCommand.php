<?php
declare(strict_types=1);

namespace Neo\Core\Console\Commands;

use Neo\Core\Console\Attribute\Command;
use Neo\Core\Console\Helper\Output;
use Neo\Core\Console\Interface\CommandInterface;
use Neo\Core\Cron\CronScanner;
use Neo\Core\DI\Container;

#[Command(
    name: 'cron:list',
    description: 'List all registered cron jobs for a project'
)]
final class CronListCommand implements CommandInterface
{
    public function __construct(
        private Container $container
    ) {}

    public function execute(array $args): void
    {
        $cronsPath = $this->container->get('cronsPath');

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

    public function getName(): string
    {
        return 'cron:list';
    }

    public function getDescription(): string
    {
        return 'List all registered cron jobs for a project';
    }

    public function getHelp(): string
    {
        Output::usage('cron:list', $this->getDescription());
        Output::option('--project=<name>', 'Target project inside ./src/');
        Output::newLine();
        echo "  Examples:\n";
        Output::example('php bin/neo cron:list --project=MyApp');

        return '';
    }
}