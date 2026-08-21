<?php
declare(strict_types=1);

namespace Neo\Core\Application\Command;

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

        $buildDir = ROOT_DIR . "/public/builds/$project";
        if (is_dir($buildDir)) {
            $this->removePath($buildDir);
        }

        $projectComposerPath = ROOT_DIR . "/src/$project/composer.json";
        $packageName = null;

        if (file_exists($projectComposerPath)) {
            $json = json_decode(file_get_contents($projectComposerPath), true);
            $packageName = $json['name'] ?? null;
        }

        if (!$packageName) {
            $packageName = strtolower($project) . '/app';
        }

        $localComposerPath = ROOT_DIR . '/composer.local.json';
        if (file_exists($localComposerPath)) {
            $data = json_decode(file_get_contents($localComposerPath), true);
            if (is_array($data)) {
                unset($data['require'][$packageName]);

                $repoUrl = "src/$project";
                $data['repositories'] = array_values(array_filter(
                    $data['repositories'] ?? [],
                    fn($r) => trim($r['url'] ?? '', '/') !== trim($repoUrl, '/')
                ));

                file_put_contents(
                    $localComposerPath,
                    json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n"
                );
            } else {
                $errors++;
            }
        }

        [$vendorFolder] = explode('/', $packageName);
        $vendorPath = ROOT_DIR . "/vendor/$vendorFolder";
        $this->removePath($vendorPath);

        $srcDir = ROOT_DIR . "/src/$project";
        $this->removePath($srcDir);

        if ($errors > 0) {
            Output::warning("Project '$project' deleted with errors.");
            return ExitCode::FAILURE;
        }

        Output::success("Project '$project' deleted successfully.");
        Output::info("Running 'composer update'…");

        passthru('composer update --working-dir=' . escapeshellarg(ROOT_DIR) . ' --optimize-autoloader', $code);

        return $code === 0 ? ExitCode::SUCCESS : ExitCode::FAILURE;
    }

    private function removePath(string $path): void
    {
        if (!file_exists($path) && !is_link($path)) {
            return;
        }

        if (is_link($path)) {
            PHP_OS_FAMILY === 'Windows' ? rmdir($path) : unlink($path);
            return;
        }

        if (is_dir($path)) {
            try {
                Fs::deleteDir($path);
            } catch (\Throwable) {
            }

            if (is_dir($path) || is_link($path)) {
                PHP_OS_FAMILY === 'Windows'
                    ? exec('rmdir /s /q ' . escapeshellarg($path))
                    : exec('rm -rf ' . escapeshellarg($path));
            }
        }
    }
}