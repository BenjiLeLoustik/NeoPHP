<?php
declare(strict_types=1);

namespace Neo\Core\Console\Commands;

use Neo\Core\Console\Helper\Args;
use Neo\Core\Console\Helper\Fs;
use Neo\Core\Console\Helper\Output;
use Neo\Core\Console\Interface\CommandInterface;
use Neo\Core\Console\Attribute\Command;
use RecursiveIteratorIterator;
use RecursiveDirectoryIterator;

#[Command(
    name: 'cache:clear',
    description: 'Clear the cache of a project'
)]
final class CacheClearCommand implements CommandInterface
{

    public function execute(array $args): void
    {
        $project = Args::option($args, '--project');

        if (!$project) {
            Output::error('Missing required option: --project');
            Output::muted('Usage: php bin/neo cache:clear --project=<name>');
            return;
        }

        $cacheDir = ROOT_DIR . "/src/$project/Storage/var/cache";

        if (!is_dir($cacheDir)) {
            Output::warning("Cache directory for project '$project' does not exist.");
            return;
        }

        Fs::emptyDir($cacheDir);

        Output::success("Cache cleared for project '$project'.");
    }

    public function getName(): string
    {
        return 'cache:clear';
    }

    public function getDescription(): string
    {
        return 'Clear the cache of a project';
    }

    public function getHelp(): string
    {
        Output::usage('cache:clear', $this->getDescription());
        Output::option('--project=<name>', 'Target project inside ./src/');
        Output::newLine();
        echo "  Examples:\n";
        Output::example('php bin/neo cache:clear --project=NeoAdmin');

        return '';
    }
}
