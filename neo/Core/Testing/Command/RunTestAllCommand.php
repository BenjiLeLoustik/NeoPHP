<?php
declare(strict_types=1);

namespace Neo\Core\Testing\Command;

use Neo\Core\Console\Abstract\AbstractCommand;
use Neo\Core\Console\Attribute\Command;
use Neo\Core\Console\Enum\ExitCode;
use Neo\Core\Console\Input\Input;
use Neo\Core\Console\Input\InputOption;
use Neo\Core\Console\Output\Output;

#[Command(
    name: 'run:test:all',
    description: 'Run all PHPUnit tests for a project',
    category: 'Testing',
)]
final class RunTestAllCommand extends AbstractCommand
{
    public function configure(): void
    {
        $this->addOption(
            name: 'project',
            shortcut: null,
            mode: InputOption::REQUIRED,
            description: 'Target project',
        );

        $this->addOption(
            name: 'format',
            shortcut: null,
            mode: InputOption::REQUIRED,
            description: 'Output format (console, html, both)',
        );

        $this->addOption(
            name: 'coverage',
            shortcut: null,
            mode: InputOption::NONE,
            description: 'Generate coverage report',
        );

        $this->addOption(
            name: 'stop-on-failure',
            shortcut: null,
            mode: InputOption::NONE,
            description: 'Stop at first failure',
        );
    }

    public function do(Input $input, Output $output): ExitCode
    {
        $project = $input->getOption('project');
        $format = strtolower($input->getOption('format') ?? 'console');
        $withCoverage = (bool) $input->getOption('coverage');
        $stopOnFailure = (bool) $input->getOption('stop-on-failure');

        $basePath = ROOT_DIR . "/src/$project";
        $testsPath = "$basePath/Tests";

        if (!is_dir($testsPath) || !file_exists("$testsPath/phpunit.xml")) {
            Output::error("Project or phpunit.xml not found.");
            return ExitCode::FAILURE;
        }

        if (!file_exists(ROOT_DIR . '/vendor/bin/phpunit')) {
            Output::error('PHPUnit not found.');
            return ExitCode::FAILURE;
        }

        $reportsPath = "$basePath/Storage/reports";
        if ((in_array($format, ['html', 'both'], true) || $withCoverage) && !is_dir($reportsPath)) {
            mkdir($reportsPath, 0777, true);
        }

        $cmd = [
            ROOT_DIR . '/vendor/bin/phpunit',
            '--configuration',
            "$testsPath/phpunit.xml",
            '--colors=always',
            '--testdox'
        ];

        if ($stopOnFailure) {
            $cmd[] = '--stop-on-failure';
        }

        if (in_array($format, ['html', 'both'], true)) {
            $cmd[] = '--log-junit=' . "$reportsPath/junit.xml";
        }

        if ($withCoverage) {
            if (extension_loaded('xdebug') || extension_loaded('pcov')) {
                array_push($cmd, '--coverage-html', "$reportsPath/coverage", '--coverage-text');
            } else {
                Output::warning('Coverage driver required.');
            }
        }

        Output::title("Running tests for '$project'");
        $start = microtime(true);
        array_map('escapeshellarg', $cmd)
            |> (fn($x) => implode(' ', $x))
            |> (fn($x) => passthru($x, $exitCode));

        $duration = round(microtime(true) - $start, 2);

        if (in_array($format, ['html', 'both'], true)) {
            $this->generateHtmlSummary($reportsPath, $project, $exitCode, $duration);
        }

        return $exitCode === 0 ? ExitCode::SUCCESS : ExitCode::FAILURE;
    }

    private function generateHtmlSummary(string $path, string $proj, int $code, float $dur): void
    {
        if (!file_exists("$path/junit.xml")) {
            return;
        }

        $xml = simplexml_load_file("$path/junit.xml");
        $s = $xml->testsuite ?? null;

        $html = "<html><body><h1>Report $proj</h1><p>Duration: {$dur}s</p>";
        $html .= "<div>Passed: " . ((int)$s['tests'] - (int)$s['failures']) . "</div>";
        $html .= "</body></html>";

        file_put_contents("$path/index.html", $html);
        Output::success("HTML report generated.");
    }

    protected function getAvailableProjects(): array
    {
        return glob(ROOT_DIR . '/src/*', GLOB_ONLYDIR)
                |> (fn (array $d): array => array_map(basename(...), $d));
    }
}