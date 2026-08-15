<?php
declare(strict_types=1);

namespace Neo\Core\Application\Command;

use Neo\Core\Console\Abstract\AbstractCommand;
use Neo\Core\Console\Attribute\Command;
use Neo\Core\Console\Enum\ExitCode;
use Neo\Core\Console\Input\Input;
use Neo\Core\Console\Input\InputOption;
use Neo\Core\Console\Output\Output;

#[Command(
    name: 'project:sync',
    description: 'Sync composer.local.json with all projects in ./src/',
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
        $localComposerPath = ROOT_DIR . '/composer.local.json';
        $distPath = ROOT_DIR . '/composer.local.json.dist';

        if (!file_exists($localComposerPath)) {
            $seed = file_exists($distPath)
                ? file_get_contents($distPath)
                : json_encode(['repositories' => [], 'require' => []], JSON_PRETTY_PRINT);

            file_put_contents($localComposerPath, $seed);
        }

        $projects = glob($srcDir . '*', GLOB_ONLYDIR)
                |> (fn (array $dirs): array => array_filter($dirs, fn (string $dir) => file_exists($dir . '/composer.json')));

        if (empty($projects)) {
            Output::warning('No projects found.');
            return ExitCode::SUCCESS;
        }

        $synced = 0;
        $skipped = 0;

        foreach ($projects as $projectDir) {
            if ($this->registerInLocalComposer($localComposerPath, basename($projectDir))) {
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

    private function registerInLocalComposer(string $path, string $name): bool
    {
        $data = $path
                |> file_get_contents(...)
                |> (fn (string $c): mixed => json_decode($c, true));

        if (!is_array($data)) {
            $data = ['repositories' => [], 'require' => []];
        }

        $data['repositories'] ??= [];
        $data['require'] ??= [];

        $repoUrl = 'src/' . $name;

        $exists = $data['repositories']
                |> (fn (array $r): array => array_filter($r, fn ($x) => ($x['url'] ?? '') === $repoUrl));

        if (!empty($exists)) {
            return false;
        }

        $data['repositories'][] = [
            'type' => 'path',
            'url' => $repoUrl,
            'options' => ['symlink' => true]
        ];
        $data['require'][strtolower($name) . '/app'] = '@dev';

        $content = $data
                |> (fn (array $d): string => json_encode($d, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n");

        file_put_contents($path, $content);
        return true;
    }
}