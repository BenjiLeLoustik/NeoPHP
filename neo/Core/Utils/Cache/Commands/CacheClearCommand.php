<?php
declare(strict_types=1);

namespace Neo\Core\Utils\Cache\Commands;

use Neo\Core\Console\AbstractCommand;
use Neo\Core\Console\Attribute\Command;
use Neo\Core\Console\Enum\ExitCode;
use Neo\Core\Console\Helper\Fs;
use Neo\Core\Console\Input\Input;
use Neo\Core\Console\Input\InputOption;
use Neo\Core\Console\Output\Output;

#[Command(
    name: 'cache:clear',
    description: 'Clear the cache of a project',
    category: 'Cache',
)]
final class CacheClearCommand extends AbstractCommand
{
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
        $cacheDir = ROOT_DIR . "/src/$project/Storage/var/cache";

        if (!is_dir($cacheDir)) {
            Output::warning("Cache directory not found.");
            return ExitCode::FAILURE;
        }

        if (!Input::confirm("Clear cache for '$project' ?", false)) {
            Output::muted('Cancelled.');
            return ExitCode::SUCCESS;
        }

        Fs::emptyDir($cacheDir);
        Output::success("Cache cleared for '$project'.");

        return ExitCode::SUCCESS;
    }

    protected function getAvailableProjects(): array
    {
        return array_map('basename', glob(ROOT_DIR . '/src/*', GLOB_ONLYDIR));
    }
}