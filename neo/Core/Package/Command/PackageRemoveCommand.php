<?php

declare(strict_types=1);

namespace Neo\Core\Package\Command;

use Neo\Core\Console\Abstract\AbstractCommand;
use Neo\Core\Console\Attribute\Command;
use Neo\Core\Console\Enum\ExitCode;
use Neo\Core\Console\Input\Input;
use Neo\Core\Console\Input\InputArgument;
use Neo\Core\Console\Input\InputOption;
use Neo\Core\Console\Output\Output;
use Neo\Core\Package\Interface\PackageInterface;
use Neo\Core\Utils\Scanner\ScannerFileManager;

#[Command(
    name: 'package:remove',
    description: 'Remove a Composer package from a project',
    category: 'Package',
)]
final class PackageRemoveCommand extends AbstractCommand
{
    public function configure(): void
    {
        $this->addArgument(
            name: 'package',
            description: 'Composer package name (e.g. vendor-name/hello-package)',
            mode: InputArgument::OPTIONAL,
        );

        $this->addOption(
            name: 'project',
            shortcut: null,
            mode: InputOption::REQUIRED,
            description: 'Target project inside ./src/',
        );
    }

    public function do(Input $input, Output $output): ExitCode
    {
        $package = $input->getArgument('package') ?? Input::ask('Composer package name ? (e.g. vendor-name/hello-package)');
        $projectName = $input->getOption('project');

        if (!$package) {
            Output::error('Package name is required.');
            return ExitCode::INVALID;
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

        $composer = $composerPath
                |> file_get_contents(...)
                |> (fn (string $c): mixed => json_decode($c, true));

        if (!isset($composer['require'][$package])) {
            Output::warning("Package '$package' is not present in '$projectName'.");
            return ExitCode::SUCCESS;
        }

        $packageClasses = $this->findPackageInterfaceClasses($package);

        if (!Input::confirm("Remove '$package' from '$projectName' ?", false)) {
            Output::muted('Cancelled.');
            return ExitCode::SUCCESS;
        }

        unset($composer['require'][$package]);

        file_put_contents(
            $composerPath,
            json_encode($composer, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n"
        );

        Output::success("Package '$package' removed from ./src/$projectName/composer.json");
        Output::info('Running composer update…');

        $cmdOutput = shell_exec('composer update 2>&1');
        echo $cmdOutput . "\n";

        Output::success('Composer update done.');

        if ($packageClasses === []) {
            return ExitCode::SUCCESS;
        }

        Output::newLine();
        Output::title('Manual cleanup needed');

        Output::info("Remove the following from src/$projectName/Config/app.config.php's 'packages' array:");
        Output::newLine();
        foreach ($packageClasses as $class) {
            echo '    \\' . $class . "::class,\n";
        }

        Output::newLine();
        Output::warning("Config left untouched at src/$projectName/Config/Packages/ — remove it manually if no longer needed.");

        return ExitCode::SUCCESS;
    }

    /**
     * @return list<class-string<PackageInterface>>
     */
    private function findPackageInterfaceClasses(string $composerPackageName): array
    {
        $installedPath = ROOT_DIR . '/vendor/composer/installed.php';

        if (!file_exists($installedPath)) {
            return [];
        }

        /** @var array{versions?: array<string, array{install_path?: string}>} $installed */
        $installed = require $installedPath;
        $rawPath = $installed['versions'][$composerPackageName]['install_path'] ?? null;

        if ($rawPath === null) {
            return [];
        }

        $resolvedPath = realpath($rawPath);

        if ($resolvedPath === false || !is_dir($resolvedPath)) {
            return [];
        }

        $results = new ScannerFileManager()
            ->paths([$resolvedPath])
            ->withExcludedSegment('vendor', '.git', 'node_modules')
            ->scan();

        $classes = [];

        foreach ($results as $result) {
            $fqcn = $result->getFqcn();

            if (!class_exists($fqcn)) {
                continue;
            }

            $implements = class_implements($fqcn) ?: [];

            if (in_array(PackageInterface::class, $implements, true)) {
                $classes[] = $fqcn;
            }
        }

        return $classes;
    }
}