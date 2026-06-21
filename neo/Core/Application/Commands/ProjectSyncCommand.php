<?php
declare(strict_types=1);

namespace Neo\Core\Application\Commands;

use Neo\Core\Console\Abstract\AbstractCommand;
use Neo\Core\Console\Attribute\Command;
use Neo\Core\Console\Enum\ExitCode;
use Neo\Core\Console\Input\Input;
use Neo\Core\Console\Input\InputOption;
use Neo\Core\Console\Output\Output;

#[Command(
    name: 'project:sync',
    description: 'Sync root composer.json with all projects in ./src/',
    category: 'Project'
)]
final class ProjectSyncCommand extends AbstractCommand
{
    public function configure(): void
    {
        $this->addOption(
            name: 'run-composer',
            mode: InputOption::NONE,
            description: 'Run composer update automatically'
        );
    }

    public function do(Input $input, Output $output): ExitCode
    {
        $runComposer = (bool) $input->getOption('run-composer');
        $srcDir = ROOT_DIR . '/src/';
        $rootComposerPath = ROOT_DIR . '/composer.json';

        if (!file_exists($rootComposerPath)) {
            Output::error("Root composer.json not found.");
            return ExitCode::FAILURE;
        }

        $projects = array_filter(
            glob($srcDir . '*', GLOB_ONLYDIR),
            fn(string $dir) => file_exists($dir . '/composer.json')
        );

        if (empty($projects)) {
            Output::warning('No projects found.');
            return ExitCode::SUCCESS;
        }

        $synced = 0;
        $skipped = 0;

        foreach ($projects as $projectDir) {
            if ($this->registerInRootComposer($rootComposerPath, basename($projectDir))) {
                $synced++;
            } else {
                $skipped++;
            }
        }

        Output::info("Sync done: $synced added, $skipped already present.");

        if ($runComposer) {
            Output::info('Running composer update…');
            passthru('composer update', $code);
            return $code === 0 ? ExitCode::SUCCESS : ExitCode::FAILURE;
        }

        return ExitCode::SUCCESS;
    }

    private function registerInRootComposer(string $path, string $name): bool
    {
        $data = json_decode(file_get_contents($path), true);
        $repoUrl = 'src/' . $name;

        $exists = array_filter($data['repositories'] ?? [], fn($r) => ($r['url'] ?? '') === $repoUrl);
        if (!empty($exists)) return false;

        $data['repositories'][] = [
            'type' => 'path',
            'url' => $repoUrl,
            'options' => ['symlink' => false]
        ];
        $data['require'][strtolower($name) . '/app'] = '@dev';
        $data['minimum-stability'] = 'dev';
        $data['prefer-stable'] = true;

        file_put_contents($path, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n");
        return true;
    }
}