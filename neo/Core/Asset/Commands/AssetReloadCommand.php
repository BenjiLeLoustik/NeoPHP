<?php
declare(strict_types=1);

namespace Neo\Core\Asset\Commands;


use Neo\Core\Console\Attribute\Command;
use Neo\Core\Console\Helper\Args;
use Neo\Core\Console\Helper\Fs;
use Neo\Core\Console\Helper\Output;
use Neo\Core\Console\Interface\CommandInterface;

#[Command(
    name: 'asset:reload',
    description: 'Delete the build folder of a project'
)]
final class AssetReloadCommand implements CommandInterface
{

    public function execute(array $args): void
    {
        $project = Args::option($args, '--project');

        if (!$project) {
            Output::error('Missing required option: --project');
            Output::muted('Usage: php bin/neo asset:reload --project=<name>');
            return;
        }

        $buildDir = ROOT_DIR . "/public/builds/{$project}";
        if (!is_dir($buildDir)) {
            Output::warning("Build folder for project '$project' does not exist.");
            return;
        }

        Fs::deleteDir($buildDir);

        Output::success("Build folder deleted for project '$project'.");
    }

    public function getName(): string
    {
        return 'asset:reload';
    }

    public function getDescription(): string
    {
        return 'Delete the build folder of a project';
    }

    public function getHelp(): string
    {
        Output::usage('asset:reload', $this->getDescription());
        Output::option('--project=<name>', 'Project whose build folder should be deleted');
        Output::newLine();
        echo "  Examples:\n";
        Output::example('php bin/neo asset:reload --project=NeoAdmin');

        return '';
    }
}
