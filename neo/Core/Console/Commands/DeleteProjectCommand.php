<?php
declare(strict_types=1);

namespace Neo\Core\Console\Commands;

use Neo\Core\Console\Attribute\Command;
use Neo\Core\Console\Interface\CommandInterface;
use Neo\Core\Console\Helper\Args;
use Neo\Core\Console\Helper\Fs;
use Neo\Core\Console\Helper\Output;

#[Command(name: 'app:delete:project', description: 'Delete a NeoPHP project')]
final class DeleteProjectCommand implements CommandInterface
{
    public function execute(array $args): void
    {
        $project = Args::positional($args, 0);

        if (!$project) {
            Output::error('Missing argument: <ProjectName>');
            Output::muted('Usage: php bin/neo delete:project <ProjectName>');
            return;
        }

        Output::newLine();
        Output::warning("You are about to delete project '$project'. This action is irreversible.");

        if (!Output::confirm('Confirm deletion?')) {
            Output::muted('Deletion cancelled.');
            return;
        }

        $errors = 0;

        Output::step('1/3', 'Deleting build folder…');
        $buildDir = ROOT_DIR . "public/builds/$project";

        if (!is_dir($buildDir)) {
            Output::skip("No build folder found: $buildDir");
        } else {
            Fs::deleteDir($buildDir);
            Output::muted("Deleted: $buildDir");
        }

        Output::step('2/3', 'Cleaning root composer.json…');
        $composerPath = ROOT_DIR . 'composer.json';

        if (!file_exists($composerPath)) {
            Output::error("composer.json not found: $composerPath");
            $errors++;
        } else {
            $composer = json_decode(file_get_contents($composerPath), true);

            if ($composer === null) {
                Output::error('composer.json is invalid JSON.');
                $errors++;
            } else {
                $changed = false;
                $packageName = null;

                $projectComposerPath = ROOT_DIR . "src/$project/composer.json";
                if (file_exists($projectComposerPath)) {
                    $projectComposer = json_decode(file_get_contents($projectComposerPath), true);
                    $packageName = $projectComposer['name'] ?? null;
                }

                if ($packageName && isset($composer['require'][$packageName])) {
                    unset($composer['require'][$packageName]);
                    Output::muted("require: removed '$packageName'");
                    $changed = true;
                } elseif ($packageName) {
                    Output::skip("'$packageName' not found in require.");
                } else {
                    Output::warning('Cannot determine package name (project composer.json missing).');
                }

                $repoUrl = "src/$project";
                $before = count($composer['repositories'] ?? []);
                $composer['repositories'] = array_values(array_filter(
                    $composer['repositories'] ?? [],
                    fn($r) => trim($r['url'] ?? '', '/') !== trim($repoUrl, '/')
                ));
                $after = count($composer['repositories']);

                if ($before !== $after) {
                    Output::muted("repositories: removed entry '$repoUrl'");
                    $changed = true;
                } else {
                    Output::skip("No repositories entry for '$repoUrl'.");
                }

                if ($changed) {
                    file_put_contents(
                        $composerPath,
                        json_encode($composer, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n"
                    );
                    Output::muted('composer.json updated.');
                }
            }
        }

        Output::step('3/3', "Deleting src/{$project}...");
        $srcDir = ROOT_DIR . "src/$project";

        if (!is_dir($srcDir)) {
            Output::skip("Directory not found: $srcDir");
        } else {
            Fs::deleteDir($srcDir);
            Output::muted("Deleted: $srcDir");
        }

        Output::newLine();

        if ($errors > 0) {
            Output::warning("Project '$project' deleted with $errors error(s).");
        } else {
            Output::success("Project '$project' deleted successfully.");
            Output::info("Running 'composer update'…");
            passthru(
                'composer update --working-dir=' . escapeshellarg(ROOT_DIR) . ' --optimize-autoloader',
                $code
            );
            if ($code !== 0) {
                Output::error('composer update failed.');
            } else {
                Output::success('composer update done.');
            }
        }
    }

    public function getName(): string
    {
        return 'delete:project';
    }

    public function getDescription(): string
    {
        return 'Delete a NeoPHP project';
    }

    public function getHelp(): string
    {
        Output::usage('delete:project', $this->getDescription());
        Output::option('<ProjectName>', 'Name of the project to delete');
        Output::newLine();
        echo "  Examples:\n";
        Output::example('php bin/neo delete:project MonProjet');

        return '';
    }
}