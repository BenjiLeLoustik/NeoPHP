<?php
declare(strict_types=1);

namespace Neo\Core\Application\Commands;

use Neo\Core\Console\Abstract\AbstractCommand;
use Neo\Core\Console\Attribute\Command;
use Neo\Core\Console\Enum\ExitCode;
use Neo\Core\Console\Helper\Fs;
use Neo\Core\Console\Input\Input;
use Neo\Core\Console\Input\InputArgument;
use Neo\Core\Console\Output\Output;

#[Command(
    name: 'project:delete',
    description: 'Delete a NeoPHP project',
    category: 'Project'
)]
final class ProjectDeleteCommand extends AbstractCommand
{
    public function configure(): void
    {
        $this->addArgument(
            name: 'projectName',
            description: 'Name of the project to delete',
            mode: InputArgument::REQUIRED
        );
    }

    public function do(Input $input, Output $output): ExitCode
    {
        $project = $input->getArgument('projectName');
        $project = Fs::pascalCase($project);

        Output::warning("You are about to delete project '$project'. This action is irreversible.");
        if (!Input::confirm('Confirm deletion?', false)) {
            Output::muted('Deletion cancelled.');
            return ExitCode::SUCCESS;
        }

        $errors = 0;

        $buildDir = ROOT_DIR . "public/builds/$project";
        if (is_dir($buildDir)) {
            Fs::deleteDir($buildDir);
        }

        $localComposerPath = ROOT_DIR . '/composer.local.json';
        if (file_exists($localComposerPath)) {
            $data = json_decode(file_get_contents($localComposerPath), true);
            if (is_array($data)) {
                $projectComposerPath = ROOT_DIR . "src/$project/composer.json";

                $packageName = file_exists($projectComposerPath)
                    ? ($projectComposerPath
                        |> file_get_contents(...)
                        |> (fn (string $c): mixed => json_decode($c, true))
                )['name'] ?? null
                    : null;

                if ($packageName && isset($data['require'][$packageName])) {
                    unset($data['require'][$packageName]);
                }

                $repoUrl = "src/$project";
                $data['repositories'] = array_values(array_filter(
                    $data['repositories'] ?? [],
                    fn($r) => trim($r['url'] ?? '', '/') !== trim($repoUrl, '/')
                ));

                file_put_contents($localComposerPath, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n");
            } else {
                $errors++;
            }
        }

        $srcDir = ROOT_DIR . "src/$project";
        if (is_dir($srcDir)) {
            Fs::deleteDir($srcDir);
        }

        if ($errors > 0) {
            Output::warning("Project '$project' deleted with errors.");
            return ExitCode::FAILURE;
        }

        Output::success("Project '$project' deleted successfully.");
        Output::info("Running 'composer update'…");

        passthru('composer update --working-dir=' . escapeshellarg(ROOT_DIR) . ' --optimize-autoloader', $code);

        return $code === 0 ? ExitCode::SUCCESS : ExitCode::FAILURE;
    }
}