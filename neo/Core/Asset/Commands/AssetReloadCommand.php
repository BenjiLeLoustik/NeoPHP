<?php
declare(strict_types=1);

namespace Neo\Core\Asset\Commands;

use Neo\Core\Console\Abstract\AbstractCommand;
use Neo\Core\Console\Attribute\Command;
use Neo\Core\Console\Enum\ExitCode;
use Neo\Core\Console\Helper\Fs;
use Neo\Core\Console\Input\Input;
use Neo\Core\Console\Input\InputOption;
use Neo\Core\Console\Output\Output;

#[Command(
    name: 'asset:reload',
    description: 'Delete the build folder of a project',
    category: 'Asset'
)]
final class AssetReloadCommand extends AbstractCommand
{
    public function configure(): void
    {
        $this->addOption(
            name: 'project',
            mode: InputOption::REQUIRED,
            description: 'Project whose build folder should be deleted'
        );
    }

    public function do(Input $input, Output $output): ExitCode
    {
        $project = $input->getOption('project');

        if (!$project) {
            $projects = $this->getAvailableProjects();
            if (empty($projects)) {
                Output::error('No projects found.');
                return ExitCode::FAILURE;
            }
            $project = Input::choice('Which project do you want to reload ?', $projects);
        }

        $buildDir = ROOT_DIR . "/public/builds/{$project}";

        if (!is_dir($buildDir)) {
            Output::warning("Build folder for project '$project' does not exist.");
            return ExitCode::FAILURE;
        }

        if (!Input::confirm("Delete build folder for '$project' ?", false)) {
            Output::muted('Cancelled.');
            return ExitCode::SUCCESS;
        }

        Fs::deleteDir($buildDir);
        Output::success("Build folder deleted for project '$project'.");

        return ExitCode::SUCCESS;
    }
}