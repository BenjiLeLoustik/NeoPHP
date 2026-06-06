<?php
declare(strict_types=1);

namespace Neo\Core\Asset\Commands;

use Neo\Core\Console\AbstractCommand;
use Neo\Core\Console\Attribute\Command;
use Neo\Core\Console\Helper\Args;
use Neo\Core\Console\Helper\Fs;
use Neo\Core\Console\Helper\Input;
use Neo\Core\Console\Helper\Output;

#[Command(
    name: 'asset:reload',
    description: 'Delete the build folder of a project',
    category: 'Asset'
)]
final class AssetReloadCommand extends AbstractCommand
{
    public function execute(array $args): void
    {
        $project = Args::option($args, '--project');

        if (!$project) {
            $projects = $this->getAvailableProjects();

            if (empty($projects)) {
                Output::error('No projects found in ./public/builds/');
                return;
            }

            $project = Input::choice('Which project do you want to reload ?', $projects);
        }

        $buildDir = ROOT_DIR . "/public/builds/{$project}";

        if (!is_dir($buildDir)) {
            Output::warning("Build folder for project '$project' does not exist.");
            return;
        }

        if (!Input::confirm("Delete build folder for '$project' ?", false)) {
            Output::muted('Cancelled.');
            return;
        }

        Fs::deleteDir($buildDir);
        Output::success("Build folder deleted for project '$project'.");
    }

    public function getHelp(): string
    {
        Output::usage('asset:reload', $this->getDescription());
        Output::option('--project=<name>', 'Project whose build folder should be deleted (interactive selection if omitted)');
        Output::newLine();
        echo "  Examples:\n";
        Output::example('php bin/neo asset:reload --project=NeoAdmin');
        Output::example('php bin/neo asset:reload');

        return '';
    }
}