<?php
declare(strict_types=1);

namespace Neo\Core\Utils\Cache\Commands;

use Neo\Core\Console\Attribute\Command;
use Neo\Core\Console\Helper\Args;
use Neo\Core\Console\Helper\Fs;
use Neo\Core\Console\Helper\Input;
use Neo\Core\Console\Helper\Output;
use Neo\Core\Console\Interface\CommandInterface;

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
            $projects = $this->getAvailableProjects();

            if (empty($projects)) {
                Output::error('No projects found in ./src/');
                return;
            }

            $project = Input::choice('Target project ?', $projects);
        }

        $cacheDir = ROOT_DIR . "/src/$project/Storage/var/cache";

        if (!is_dir($cacheDir)) {
            Output::warning("Cache directory for project '$project' does not exist.");
            return;
        }

        if (!Input::confirm("Clear cache for '$project' ?", false)) {
            Output::muted('Cancelled.');
            return;
        }

        Fs::emptyDir($cacheDir);
        Output::success("Cache cleared for project '$project'.");
    }

    private function getAvailableProjects(): array
    {
        $srcDir = ROOT_DIR . '/src/';

        if (!is_dir($srcDir)) {
            return [];
        }

        return array_map(
            fn(string $dir) => basename($dir),
            glob($srcDir . '*', GLOB_ONLYDIR) ?: []
        );
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
        Output::option('--project=<name>', 'Target project inside ./src/ (interactive selection if omitted)');
        Output::newLine();
        echo "  Examples:\n";
        Output::example('php bin/neo cache:clear --project=NeoAdmin');
        Output::example('php bin/neo cache:clear');

        return '';
    }
}