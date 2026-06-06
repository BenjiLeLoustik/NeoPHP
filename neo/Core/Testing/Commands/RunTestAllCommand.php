<?php
declare(strict_types=1);

namespace Neo\Core\Testing\Commands;

use Neo\Core\Console\AbstractCommand;
use Neo\Core\Console\Attribute\Command;
use Neo\Core\Console\Helper\Args;
use Neo\Core\Console\Helper\Input;
use Neo\Core\Console\Helper\Output;

#[Command(
    name: 'run:test:all',
    description: 'Run all PHPUnit tests for a project',
    category: 'Testing'
)]
final class RunTestAllCommand extends AbstractCommand
{
    public function execute(array $args): void
    {
        $project = Args::option($args, '--project');
        $format = strtolower(Args::option($args, '--format') ?? 'console');
        $withCoverage = Args::flag($args, '--coverage');
        $stopOnFailure = Args::flag($args, '--stop-on-failure');

        if (!$project) {
            $projects = $this->getAvailableProjects();

            if (empty($projects)) {
                Output::error('No projects found in ./src/');
                return;
            }

            $project = Input::choice('Target project ?', $projects);
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

        $xmlConfig = "$testsPath/phpunit.xml";

        if (!file_exists($xmlConfig)) {
            Output::error("phpunit.xml not found in src/$project/Tests/. Run make:test first.");
            return;
        }

        $reportsPath = "$basePath/Storage/reports";
        if (in_array($format, ['html', 'both'], true) || $withCoverage) {
            if (!is_dir($reportsPath)) {
                mkdir($reportsPath, 0777, true);
            }
        }

        $phpunitBin = ROOT_DIR . '/vendor/bin/phpunit';

        $cmd = escapeshellarg($phpunitBin);
        $cmd .= ' --configuration ' . escapeshellarg($xmlConfig);
        $cmd .= ' --colors=always --testdox';

        if ($stopOnFailure) {
            $cmd .= ' --stop-on-failure';
        }

        if (in_array($format, ['html', 'both'], true)) {
            $cmd .= ' --log-junit ' . escapeshellarg("$reportsPath/junit.xml");
        }

        if ($withCoverage) {
            if ($this->hasCoverageDriver()) {
                $cmd .= ' --coverage-html ' . escapeshellarg("$reportsPath/coverage");
                $cmd .= ' --coverage-text';
            } else {
                Output::warning('Xdebug or PCOV required for coverage. --coverage ignored.');
            }
        }

        Output::title("Running all tests for project '$project'");

        $startTime = microtime(true);
        passthru($cmd, $exitCode);
        $duration = round(microtime(true) - $startTime, 2);

        Output::separator();
        Output::muted("Duration: {$duration}s");

        if (in_array($format, ['html', 'both'], true)) {
            $this->generateHtmlSummary($reportsPath, $project, $exitCode, $duration);
        }

        match (true) {
            $exitCode === 0 => Output::success('All tests passed.'),
            $exitCode === 1 => Output::warning('Completed with warnings (code 1).'),
            default => Output::error("Tests failed (code $exitCode)."),
        };
    }

    private function generateHtmlSummary(
        string $reportsPath,
        string $project,
        int $exitCode,
        float $duration
    ): void {
        $junitFile = "$reportsPath/junit.xml";

        if (!file_exists($junitFile)) {
            Output::warning('junit.xml missing — HTML report cannot be generated.');
            return;
        }

        $xml = simplexml_load_file($junitFile);
        $suite = $xml->testsuite ?? null;
        $tests = (int) ($suite['tests']    ?? 0);
        $failures = (int) ($suite['failures'] ?? 0);
        $errors = (int) ($suite['errors']   ?? 0);
        $skipped = (int) ($suite['skipped']  ?? 0);
        $passed = $tests - $failures - $errors - $skipped;

        $statusColor = match (true) {
            $exitCode === 0 => '#22c55e',
            $exitCode === 1 => '#f59e0b',
            default => '#ef4444',
        };

        $statusLabel = match (true) {
            $exitCode === 0 => 'SUCCESS',
            $exitCode === 1 => 'WARNINGS',
            default => 'FAILURE',
        };

        $date = date('d/m/Y H:i:s');
        $failuresList = '';

        foreach ($xml->xpath('//testcase[failure or error]') as $tc) {
            $name = htmlspecialchars((string) ($tc['name']      ?? ''));
            $className = htmlspecialchars((string) ($tc['classname'] ?? ''));
            $message = htmlspecialchars(substr((string) ($tc->failure ?? $tc->error ?? ''), 0, 300));
            $failuresList .= "<div class='failure'><strong>{$className}::{$name}</strong><pre>{$message}</pre></div>";
        }

        $html = <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Test Report — {$project}</title>
    <style>
        body { font-family: system-ui, sans-serif; background: #f8fafc; color: #1e293b; margin: 0; padding: 2rem; }
        h1 { font-size: 1.5rem; margin-bottom: .25rem; }
        .meta { color: #64748b; font-size: .9rem; margin-bottom: 2rem; }
        .badge { display: inline-block; padding: .3rem 1rem; border-radius: 999px; color: #fff; font-weight: 600; background: {$statusColor}; }
        .stats { display: flex; gap: 1.5rem; margin: 1.5rem 0; flex-wrap: wrap; }
        .stat { background: #fff; border: 1px solid #e2e8f0; border-radius: 8px; padding: 1rem 1.5rem; min-width: 100px; text-align: center; }
        .stat strong { display: block; font-size: 1.8rem; }
        .stat span { font-size: .8rem; color: #64748b; }
        .failure { background: #fef2f2; border-left: 4px solid #ef4444; padding: 1rem; margin-bottom: 1rem; border-radius: 4px; }
        .failure strong { display: block; margin-bottom: .5rem; }
        pre { white-space: pre-wrap; font-size: .8rem; color: #7f1d1d; margin: 0; }
        footer { margin-top: 3rem; font-size: .8rem; color: #94a3b8; }
    </style>
</head>
<body>
    <h1>Test Report — {$project}</h1>
    <p class="meta">Generated on {$date} &bull; Duration: {$duration}s</p>
    <span class="badge">{$statusLabel}</span>

    <div class="stats">
        <div class="stat"><strong>{$tests}</strong><span>Total</span></div>
        <div class="stat" style="border-color:#86efac"><strong style="color:#16a34a">{$passed}</strong><span>Passed</span></div>
        <div class="stat" style="border-color:#fca5a5"><strong style="color:#dc2626">{$failures}</strong><span>Failed</span></div>
        <div class="stat" style="border-color:#fca5a5"><strong style="color:#dc2626">{$errors}</strong><span>Errors</span></div>
        <div class="stat" style="border-color:#fde68a"><strong style="color:#d97706">{$skipped}</strong><span>Skipped</span></div>
    </div>

    <div>{$failuresList}</div>

    <footer>NeoPHP Testing — PHPUnit</footer>
</body>
</html>
HTML;

        $reportFile = "$reportsPath/index.html";
        file_put_contents($reportFile, $html);
        Output::success("HTML report generated: src/$project/Storage/reports/index.html");
    }

    private function hasCoverageDriver(): bool
    {
        return extension_loaded('xdebug') || extension_loaded('pcov');
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

    public function getHelp(): string
    {
        Output::usage('run:test:all', $this->getDescription());
        Output::option('--project=<name>', 'Target project inside ./src/ (interactive selection if omitted)');
        Output::option('--format=console', 'Console output only (default)');
        Output::option('--format=html', 'Generate HTML report in Storage/reports/');
        Output::option('--format=both', 'Console output + HTML report');
        Output::option('--coverage', 'Generate coverage report (requires Xdebug or PCOV)');
        Output::option('--stop-on-failure', 'Stop at the first failure');
        Output::newLine();
        echo "  Examples:\n";
        Output::example('php bin/neo run:test:all --project=Blog');
        Output::example('php bin/neo run:test:all --project=Blog --format=html --coverage');
        Output::example('php bin/neo run:test:all');

        return '';
    }
}