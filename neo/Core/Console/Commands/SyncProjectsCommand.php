<?php
declare(strict_types=1);

namespace Neo\Core\Console\Commands;

use Neo\Core\Console\Interface\CommandInterface;
use Neo\Core\Console\Attribute\Command;
use Neo\Core\Console\Helper\Args;
use Neo\Core\Console\Helper\Output;

#[Command(
    name: 'app:sync:projects',
    description: 'Sync root composer.json with all projects present in ./src/'
)]
final class SyncProjectsCommand implements CommandInterface
{
    public function execute(array $args): void
    {
        $runComposer = Args::flag($args, '--run-composer');
        $srcDir = ROOT_DIR . '/src/';
        $rootComposerPath = ROOT_DIR . '/composer.json';

        if (!file_exists($rootComposerPath)) {
            Output::error("Root composer.json not found at $rootComposerPath");
            return;
        }

        $projects = array_filter(
            glob($srcDir . '*', GLOB_ONLYDIR),
            fn(string $dir) => file_exists($dir . '/composer.json')
        );

        if (empty($projects)) {
            Output::warning('No projects with a composer.json found in ./src/');
            return;
        }

        $synced = 0;
        $skipped = 0;

        foreach ($projects as $projectDir) {
            $name = basename($projectDir);
            $result = $this->registerInRootComposer($rootComposerPath, $name);

            if ($result) {
                Output::success("$name added to root composer.json.");
                $synced++;
            } else {
                Output::skip("$name already present.");
                $skipped++;
            }
        }

        Output::newLine();
        Output::info("Sync done: $synced project(s) added, $skipped already present.");

        if ($runComposer) {
            Output::info('Running composer update…');
            $output = shell_exec('composer update 2>&1');
            echo $output . "\n";
            Output::success('Composer update done.');
        } else {
            Output::muted('Remember to run: composer update');
            Output::muted('Or re-run with: php bin/neo sync:projects --run-composer');
        }
    }

    private function registerInRootComposer(string $rootComposerPath, string $name): bool
    {
        $packageName = strtolower($name) . '/app';
        $rootComposer = json_decode(file_get_contents($rootComposerPath), true);
        $repositories = $rootComposer['repositories'] ?? [];

        $alreadyExists = array_filter(
            $repositories,
            fn($repo) => ($repo['url'] ?? '') === 'src/' . $name
        );

        if (!empty($alreadyExists)) {
            return false;
        }

        $rootComposer['minimum-stability'] = 'dev';
        $rootComposer['prefer-stable']     = true;
        $rootComposer['repositories'][]    = [
            'type' => 'path',
            'url' => 'src/' . $name,
            'options' => ['symlink' => false],
        ];
        $rootComposer['require'][$packageName] = '@dev';

        file_put_contents(
            $rootComposerPath,
            json_encode($rootComposer, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n"
        );

        return true;
    }

    public function getName(): string
    {
        return 'sync:projects';
    }

    public function getDescription(): string
    {
        return 'Sync root composer.json with all projects present in ./src/';
    }

    public function getHelp(): string
    {
        Output::usage('sync:projects', $this->getDescription());
        Output::option('--run-composer', 'Run `composer update` automatically after sync');
        Output::newLine();
        Output::muted('  Useful after a git pull that resets the root composer.json.');
        Output::newLine();
        echo "  Examples:\n";
        Output::example('php bin/neo sync:projects');
        Output::example('php bin/neo sync:projects --run-composer');

        return '';
    }
}