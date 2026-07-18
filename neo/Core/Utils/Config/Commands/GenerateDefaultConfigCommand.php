<?php
declare(strict_types=1);

namespace Neo\Core\Utils\Config\Commands;

use Neo\Core\Console\Abstract\AbstractCommand;
use Neo\Core\Console\Attribute\Command;
use Neo\Core\Console\Enum\ExitCode;
use Neo\Core\Console\Helper\Fs;
use Neo\Core\Console\Input\Input;
use Neo\Core\Console\Input\InputOption;
use Neo\Core\Console\Output\Output;
use Neo\Core\Utils\Config\ConfigTemplateWriter;
use Neo\Core\Utils\Config\Templates\ApiConfigTemplate;
use Neo\Core\Utils\Config\Templates\AuthConfigTemplate;
use Neo\Core\Utils\Config\Templates\DatabaseConfigTemplate;

#[Command(
    name: 'generate:default:config',
    description: 'Generate sensitive config files for a project',
    category: 'Config',
)]
final class GenerateDefaultConfigCommand extends AbstractCommand
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
        $projectPath = ROOT_DIR . "/src/$project";

        if (!is_dir($projectPath)) {
            Output::error("Project '$project' not found.");
            return ExitCode::FAILURE;
        }

        $configPath = "$projectPath/Config/";
        Fs::ensureDir($configPath);

        Output::title("Generating configs for: $project");

        ConfigTemplateWriter::write(
            templates: [
                new DatabaseConfigTemplate(),
                new ApiConfigTemplate(),
                new AuthConfigTemplate(),
            ],
            configPath: $configPath,
            projectName: $project,
        );

        return ExitCode::SUCCESS;
    }

    protected function getAvailableProjects(): array
    {
        return array_map('basename', glob(ROOT_DIR . '/src/*', GLOB_ONLYDIR));
    }
}