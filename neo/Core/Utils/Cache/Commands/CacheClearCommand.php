<?php
declare(strict_types=1);

namespace Neo\Core\Utils\Cache\Commands;

use Neo\Core\Console\AbstractCommand;
use Neo\Core\Console\Attribute\Command;
use Neo\Core\Console\Helper\Args;
use Neo\Core\Console\Helper\Fs;
use Neo\Core\Console\Helper\Input;
use Neo\Core\Console\Helper\Output;

#[Command(
    name: 'cache:clear',
    description: 'Clear the cache of a project',
    category: 'Cache'
)]
final class CacheClearCommand extends AbstractCommand
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

    public function getHelp(): string
    {
        Output::usage($this->getName(), $this->getDescription());
        Output::option('--project=<name>', 'Target project inside ./src/ (interactive selection if omitted)');
        Output::newLine();
        echo "  Examples:\n";
        Output::example("php bin/neo {$this->getName()} --project=MyApp");
        Output::example("php bin/neo {$this->getName()}");

        return '';
    }
}