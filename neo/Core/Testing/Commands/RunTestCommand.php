<?php
declare(strict_types=1);

namespace Neo\Core\Testing\Commands;

use Neo\Core\Console\Abstract\AbstractCommand;
use Neo\Core\Console\Attribute\Command;
use Neo\Core\Console\Enum\ExitCode;
use Neo\Core\Console\Input\Input;
use Neo\Core\Console\Input\InputArgument;
use Neo\Core\Console\Input\InputOption;
use Neo\Core\Console\Output\Output;

#[Command(
    name: 'run:test',
    description: 'Run a targeted PHPUnit test for a project',
    category: 'Testing',
)]
final class RunTestCommand extends AbstractCommand
{
    public function configure(): void
    {
        $this->addArgument(
            name: 'testName',
            description: 'Test class name',
            mode: InputArgument::OPTIONAL,
        );

        $this->addOption(
            name: 'project',
            shortcut: null,
            mode: InputOption::REQUIRED,
            description: 'Target project',
        );

        $this->addOption(
            name: 'filter',
            shortcut: null,
            mode: InputOption::REQUIRED,
            description: 'Method filter',
        );

        $this->addOption(
            name: 'type',
            shortcut: null,
            mode: InputOption::REQUIRED,
            description: 'Test type (unit, feature, database, middleware)',
        );
    }

    public function do(Input $input, Output $output): ExitCode
    {
        $testName = $input->getArgument('testName') ?? Input::ask('Test name ?');
        if (!$testName) return ExitCode::INVALID;

        $project = $input->getOption('project');
        $filter = $input->getOption('filter');
        $type = $input->getOption('type');

        $basePath = ROOT_DIR . "/src/$project";
        $testsPath = "$basePath/Tests";

        if (!is_dir($testsPath) || !file_exists(ROOT_DIR . '/vendor/bin/phpunit')) {
            Output::error("Tests folder or PHPUnit not found.");
            return ExitCode::FAILURE;
        }

        $testName = str_ends_with($testName, 'Test') ? $testName : $testName . 'Test';
        $testFile = $this->findTestFile($testsPath, $testName, $type);

        if (!$testFile) {
            Output::error("Test file '$testName.php' not found.");
            return ExitCode::FAILURE;
        }

        $cmd = [
            ROOT_DIR . '/vendor/bin/phpunit',
            '--configuration', "$testsPath/phpunit.xml",
            escapeshellarg($testFile),
            '--colors=always',
            '--testdox'
        ];

        if ($filter) {
            array_push($cmd, '--filter', escapeshellarg($filter));
        }

        Output::title("Running test: $testName");
        passthru(implode(' ', $cmd), $exitCode);

        return $exitCode === 0 ? ExitCode::SUCCESS : ExitCode::FAILURE;
    }

    private function findTestFile(string $testsPath, string $name, ?string $type): ?string
    {
        $searchDirs = $type ? ["$testsPath/" . ucfirst(strtolower($type))] : glob("$testsPath/*", GLOB_ONLYDIR);

        foreach ($searchDirs as $dir) {
            $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($dir));
            foreach ($iterator as $file) {
                if ($file->isFile() && $file->getBasename('.php') === $name) {
                    return $file->getRealPath();
                }
            }
        }
        return null;
    }

    protected function getAvailableProjects(): array
    {
        return glob(ROOT_DIR . '/src/*', GLOB_ONLYDIR)
                |> (fn (array $d): array => array_map(basename(...), $d));
    }
}