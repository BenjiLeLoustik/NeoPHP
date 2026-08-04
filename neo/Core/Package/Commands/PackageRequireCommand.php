<?php

declare(strict_types=1);

namespace Neo\Core\Package\Commands;

use Neo\Core\Console\Abstract\AbstractCommand;
use Neo\Core\Console\Attribute\Command;
use Neo\Core\Console\Enum\ExitCode;
use Neo\Core\Console\Input\Input;
use Neo\Core\Console\Input\InputArgument;
use Neo\Core\Console\Input\InputOption;
use Neo\Core\Console\Output\Output;
use Neo\Core\Console\Commands\ComposerRequireCommand;
use Neo\Core\DI\Container;
use Neo\Core\DI\Exception\ContainerException;
use Neo\Core\Package\Interface\PackageInterface;
use Neo\Core\Utils\Scanner\ScannerFileManager;

#[Command(
    name: 'package:require',
    description: 'Install a Composer package and detect whether it is a NeoPHP package',
    category: 'Package',
)]
class PackageRequireCommand extends AbstractCommand
{
    public function __construct(
        private readonly Container $container,
    ) {}

    public function configure(): void
    {
        $this->addArgument(
            name: 'package',
            description: 'Composer package name (e.g. vendor-name/hello-package)',
            mode: InputArgument::OPTIONAL,
        );

        $this->addArgument(
            name: 'version',
            description: 'Version constraint (e.g. ^1.0) — defaults to *',
            mode: InputArgument::OPTIONAL,
            default: '*',
        );

        $this->addOption(
            name: 'project',
            shortcut: null,
            mode: InputOption::REQUIRED,
            description: 'Target project inside ./src/',
        );
    }

    /**
     * @throws \ReflectionException
     * @throws ContainerException
     */
    public function do(Input $input, Output $output): ExitCode
    {
        $package = $input->getArgument('package') ?? Input::ask('Composer package name ? (e.g. vendor-name/hello-package)');
        $version = $input->getArgument('version') ?? '*';
        $projectName = $input->getOption('project');

        if (!$package) {
            Output::error('Package name is required.');
            return ExitCode::INVALID;
        }

        $projectPath = ROOT_DIR . '/src/' . $projectName;

        if (!is_dir($projectPath)) {
            Output::error("Project '$projectName' does not exist inside ./src/");
            return ExitCode::FAILURE;
        }

        $composerCommand = $this->container->make(ComposerRequireCommand::class);
        $composerCommand->configure();

        $composerArgv = [$package, $version, '--project=' . $projectName];

        $composerInput = new Input(
            $composerArgv,
            $composerCommand->getArgumentDefinitions(),
            $composerCommand->getOptionDefinitions(),
        );

        $exitCode = $composerCommand->do($composerInput, $output);

        if ($exitCode !== ExitCode::SUCCESS) {
            return $exitCode;
        }

        $packageClasses = $this->findPackageInterfaceClasses($package);

        Output::newLine();

        if ($packageClasses === []) {
            Output::info("'$package' does not appear to implement PackageInterface — nothing more to do.");
            return ExitCode::SUCCESS;
        }

        Output::title('NeoPHP package detected');

        foreach ($packageClasses as $class) {
            Output::success("Found: $class");
        }

        Output::newLine();
        Output::info("Register it in src/$projectName/Config/app.config.php under 'packages':");
        Output::newLine();

        foreach ($packageClasses as $class) {
            echo '    \\' . $class . "::class,\n";
        }

        Output::newLine();

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