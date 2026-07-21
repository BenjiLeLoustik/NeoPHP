<?php
declare(strict_types=1);

namespace Neo\Core\Console\Commands;

use Neo\Core\Console\Abstract\AbstractCommand;
use Neo\Core\Console\Attribute\Command;
use Neo\Core\Console\Enum\ExitCode;
use Neo\Core\Console\Input\Input;
use Neo\Core\Console\Input\InputArgument;
use Neo\Core\Console\Input\InputOption;
use Neo\Core\Console\Output\Output;

#[Command(
    name: 'app:composer:require',
    description: 'Add a Composer dependency to a specific project',
    category: 'Composer',
)]
final class ComposerRequireCommand extends AbstractCommand
{
    public function configure(): void
    {
        $this->addArgument(
            name: 'package',
            description: 'Package to install (e.g. stripe/stripe-php)',
            mode: InputArgument::OPTIONAL,
        );

        $this->addArgument(
            name: 'version',
            description: 'Version constraint (e.g. ^20.0, ~1.0) — defaults to *',
            mode: InputArgument::OPTIONAL,
            default: '*',
        );

        $this->addOption(
            name: 'project',
            shortcut: null,
            mode: InputOption::REQUIRED,
            description: 'Target project inside ./src/ (interactive selection if omitted)',
        );
    }

    public function do(Input $input, Output $output): ExitCode
    {
        $package = $input->getArgument('package');
        $version = $input->getArgument('version');
        $projectName = $input->getOption('project');

        if (!$package) {
            $package = Input::ask('Package name ? (e.g. stripe/stripe-php)');

            if (!$package) {
                Output::error('Package name is required.');
                return ExitCode::INVALID;
            }
        }

        if (!$version) {
            $version = Input::ask('Version constraint ?', '*');
        }

        $projectPath = ROOT_DIR . '/src/' . $projectName;
        $composerPath = $projectPath . '/composer.json';

        if (!is_dir($projectPath)) {
            Output::error("Project '$projectName' does not exist inside ./src/");
            return ExitCode::FAILURE;
        }

        if (!file_exists($composerPath)) {
            Output::error("No composer.json found in ./src/$projectName/");
            return ExitCode::FAILURE;
        }

        $composer = json_decode(file_get_contents($composerPath), true);

        if (isset($composer['require'][$package])) {
            Output::warning("Package '$package' is already present in '$projectName' ({$composer['require'][$package]}).");
            return ExitCode::SUCCESS;
        }

        $composer['require'][$package] = $version;

        file_put_contents(
            $composerPath,
            json_encode($composer, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n"
        );

        Output::success("Package '$package' ($version) added to ./src/$projectName/composer.json");
        Output::info('Running composer update…');
        $cmdOutput = shell_exec('composer update 2>&1');
        echo $cmdOutput . "\n";
        Output::success('Composer update done.');

        return ExitCode::SUCCESS;
    }
}