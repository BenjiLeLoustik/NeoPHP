<?php
declare(strict_types=1);

namespace Neo\Core\Console\Commands;

use Neo\Core\Console\Attribute\Command;
use Neo\Core\Console\Interface\CommandInterface;
use Neo\Core\Console\Helper\Args;
use Neo\Core\Console\Helper\Output;

#[Command(
    name: 'run:test',
    description: 'Run a targeted PHPUnit test for a project'
)]
final class RunTestCommand implements CommandInterface
{
    public function execute(array $args): void
    {
        $testName = Args::positional($args, 0);
        $project = Args::option($args, '--project');
        $filter = Args::option($args, '--filter');
        $type = Args::option($args, '--type');

        if (!$testName || !$project) {
            Output::error('Missing arguments.');
            Output::muted('Usage: php bin/neo run:test <TestName> --project=<name>');
            return;
        }

        $basePath = ROOT_DIR . "/src/$project";
        $testsPath = "$basePath/Tests";

        if (!is_dir($basePath)) {
            Output::error("Project '$project' does not exist inside ./src/");
            return;
        }

        if (!is_dir($testsPath)) {
            Output::error("No Tests/ folder found in src/$project/. Run make:test first.");
            return;
        }

        if (!$this->checkPhpUnit()) {
            return;
        }

        if (!str_ends_with($testName, 'Test')) {
            $testName .= 'Test';
        }

        $testFile = $this->findTestFile($testsPath, $testName, $type);

        if ($testFile === null) {
            Output::error("Test file '$testName.php' not found in src/$project/Tests/");
            return;
        }

        $phpunitBin = ROOT_DIR . '/vendor/bin/phpunit';
        $xmlConfig = "$testsPath/phpunit.xml";

        $cmd = escapeshellarg($phpunitBin);
        $cmd .= ' --configuration ' . escapeshellarg($xmlConfig);
        $cmd .= ' ' . escapeshellarg($testFile);
        $cmd .= ' --colors=always --testdox';

        if ($filter) {
            $cmd .= ' --filter ' . escapeshellarg($filter);
        }

        Output::title("Running test: $testName");
        passthru($cmd, $exitCode);
        Output::separator();

        match (true) {
            $exitCode === 0 => Output::success('All tests passed.'),
            $exitCode === 1 => Output::warning('Completed with warnings (code 1).'),
            default => Output::error("Tests failed (code $exitCode)."),
        };
    }

    private function findTestFile(string $testsPath, string $testName, ?string $type): ?string
    {
        $searchDirs = $type
            ? ["$testsPath/" . ucfirst(strtolower($type))]
            : ["$testsPath/Unit", "$testsPath/Feature", "$testsPath/Database", "$testsPath/Middleware"];

        foreach ($searchDirs as $dir) {
            if (!is_dir($dir)) {
                continue;
            }

            $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($dir));

            foreach ($iterator as $file) {
                if ($file->isFile()
                    && $file->getExtension() === 'php'
                    && $file->getBasename('.php') === $testName
                ) {
                    return $file->getRealPath();
                }
            }
        }

        return null;
    }

    private function checkPhpUnit(): bool
    {
        $phpunitBin = ROOT_DIR . '/vendor/bin/phpunit';

        if (!file_exists($phpunitBin)) {
            Output::error('PHPUnit not found. Run: composer require --dev phpunit/phpunit');
            return false;
        }

        return true;
    }

    public function getName(): string
    {
        return 'run:test';
    }

    public function getDescription(): string
    {
        return 'Run a targeted PHPUnit test for a project';
    }

    public function getHelp(): string
    {
        Output::usage('run:test', $this->getDescription());
        Output::option('<TestName>',        'Test class name (e.g. UserServiceTest)');
        Output::option('--project=<name>',  'Target project inside ./src/');
        Output::option('--filter=<method>', 'Filter on a specific test method');
        Output::option('--type=<type>',     'Search only inside Tests/<Type>/ (unit|feature|database|middleware)');
        Output::newLine();
        echo "  Examples:\n";
        Output::example('php bin/neo run:test UserServiceTest --project=Blog');
        Output::example('php bin/neo run:test UserServiceTest --filter=test_example --project=Blog');

        return '';
    }
}