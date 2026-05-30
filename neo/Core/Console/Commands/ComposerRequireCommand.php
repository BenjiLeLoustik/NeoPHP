<?php
declare(strict_types=1);

namespace Neo\Core\Console\Commands;

use Neo\Core\Console\Interface\CommandInterface;
use Neo\Core\Console\Attribute\Command;
use Neo\Core\Console\Helper\Args;
use Neo\Core\Console\Helper\Output;

#[Command(
    name: 'app:composer:require',
    description: 'Add a Composer dependency to a specific project'
)]
final class ComposerRequireCommand implements CommandInterface
{
    public function execute(array $args): void
    {
        $positionals = Args::positionals($args);
        $package = $positionals[0] ?? null;

        if (!$package) {
            Output::error('Missing argument: <package/name>');
            Output::muted('Usage: php bin/neo composer:require <package/name> [version] --project=<name>');
            return;
        }

        $version = $positionals[1] ?? '*';
        $projectName = Args::option($args, '--project');

        if (!$projectName) {
            Output::error('Missing required option: --project');
            return;
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

    public function getName(): string
    {
        return 'composer:require';
    }

    public function getDescription(): string
    {
        return 'Add a Composer dependency to a specific project';
    }

    public function getHelp(): string
    {
        Output::usage('composer:require', $this->getDescription());
        Output::option('<package/name>',    'Package to install (e.g. stripe/stripe-php)');
        Output::option('[version]',         'Version constraint (e.g. ^20.0, ~1.0) — defaults to *');
        Output::option('--project=<name>',  'Target project inside ./src/');
        Output::newLine();
        echo "  Examples:\n";
        Output::example('php bin/neo composer:require stripe/stripe-php --project=MonProjet');
        Output::example('php bin/neo composer:require stripe/stripe-php ^20.0 --project=MonProjet');

        return '';
    }
}