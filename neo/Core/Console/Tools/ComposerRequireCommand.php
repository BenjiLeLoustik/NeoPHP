<?php
declare(strict_types=1);

namespace Neo\Core\Console\Tools;

use Neo\Core\Console\AbstractCommand;
use Neo\Core\Console\Attribute\Command;
use Neo\Core\Console\Helper\Args;
use Neo\Core\Console\Helper\Input;
use Neo\Core\Console\Helper\Output;

#[Command(
    name: 'app:composer:require',
    description: 'Add a Composer dependency to a specific project',
    category: 'Composer'
)]
final class ComposerRequireCommand extends AbstractCommand
{
    public function execute(array $args): void
    {
        $positionals = Args::positionals($args);
        $package = $positionals[0] ?? null;
        $version = $positionals[1] ?? null;
        $projectName = Args::option($args, '--project');

        if (!$package) {
            $package = Input::ask('Package name ? (e.g. stripe/stripe-php)');
            if (!$package) {
                Output::error('Package name is required.');
                return;
            }
        }

        if (!$version) {
            $version = Input::ask('Version constraint ?', '*');
        }

        if (!$projectName) {
            $projects = $this->getAvailableProjects();

            if (empty($projects)) {
                Output::error('No projects found in ./src/');
                return;
            }

            $projectName = Input::choice('Target project ?', $projects);
        }

        $projectPath = ROOT_DIR . '/src/' . $projectName;
        $composerPath = $projectPath . '/composer.json';

        if (!is_dir($projectPath)) {
            Output::error("Project '$projectName' does not exist inside ./src/");
            return;
        }

        if (!file_exists($composerPath)) {
            Output::error("No composer.json found in ./src/$projectName/");
            return;
        }

        $composer = json_decode(file_get_contents($composerPath), true);

        if (isset($composer['require'][$package])) {
            Output::warning("Package '$package' is already present in '$projectName' ({$composer['require'][$package]}).");
            return;
        }

        $composer['require'][$package] = $version;

        file_put_contents(
            $composerPath,
            json_encode($composer, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n"
        );

        Output::success("Package '$package' ($version) added to ./src/$projectName/composer.json");
        Output::info('Running composer update…');
        $output = shell_exec('composer update 2>&1');
        echo $output . "\n";
        Output::success('Composer update done.');
    }

    public function getHelp(): string
    {
        Output::usage('composer:require', $this->getDescription());
        Output::option('<package/name>', 'Package to install (e.g. stripe/stripe-php)');
        Output::option('[version]', 'Version constraint (e.g. ^20.0, ~1.0) — defaults to *');
        Output::option('--project=<name>', 'Target project inside ./src/ (interactive selection if omitted)');
        Output::newLine();
        echo "  Examples:\n";
        Output::example('php bin/neo app:composer:require stripe/stripe-php --project=MonProjet');
        Output::example('php bin/neo app:composer:require stripe/stripe-php ^20.0 --project=MonProjet');
        Output::example('php bin/neo app:composer:require');

        return '';
    }
}