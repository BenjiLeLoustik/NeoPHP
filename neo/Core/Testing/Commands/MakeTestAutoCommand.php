<?php
declare(strict_types=1);

namespace Neo\Core\Testing\Commands;

use Neo\Core\Console\AbstractCommand;
use Neo\Core\Console\Attribute\Command;
use Neo\Core\Console\Helper\Args;
use Neo\Core\Console\Helper\Input;
use Neo\Core\Console\Helper\Output;
use Neo\Core\DI\Container;
use Neo\Core\Testing\Generator\TestGenerator;
use Neo\Core\Testing\Scaffold\TestScaffolder;

#[Command(
    name: 'make:test:auto',
    description: 'Auto-generate test files from #[Test] attributes',
    category: 'Testing'
)]
final class MakeTestAutoCommand extends AbstractCommand
{
    public function __construct(private Container $container) {}

    public function execute(array $args): void
    {
        $project = Args::option($args, '--project');
        $force = Args::flag($args, '--force');
        $onlyType = Args::option($args, '--only');
        $dryRun = Args::flag($args, '--dry-run');

        if (!$project) {
            $projects = $this->getAvailableProjects();

            if (empty($projects)) {
                Output::error('No projects found in ./src/');
                return;
            }

            $project = Input::choice('Target project ?', $projects);
        }

        $basePath = ROOT_DIR . "/src/$project";

        if (!is_dir($basePath)) {
            Output::error("Project '$project' does not exist inside ./src/");
            return;
        }

        (new TestScaffolder())->ensure($basePath, $project);

        Output::title("Scanning #[Test] attributes in project '$project'");

        $generator = new TestGenerator($this->container);
        $result = $generator->generate(
            force: $force,
            onlyType: $onlyType,
            dryRun: $dryRun,
        );

        if (!empty($result['generated'])) {
            $label = $dryRun ? 'Files that would be generated:' : 'Files generated:';
            Output::info($label);
            foreach ($result['generated'] as $file) {
                echo '  ' . Output::colorize('+ ', 'green') . $file . "\n";
            }
        }

        if (!empty($result['skipped'])) {
            Output::newLine();
            Output::info('Skipped:');
            foreach ($result['skipped'] as $item) {
                Output::skip($item);
            }
        }

        if (empty($result['generated']) && empty($result['skipped'])) {
            Output::warning('No #[Test] attributes found. Add #[Test] to your classes or methods.');
        }

        Output::separator();
        Output::success('Done.');
    }

    public function getHelp(): string
    {
        Output::usage($this->getName(), $this->getDescription());
        Output::option('--project=<name>', 'Target project inside ./src/ (interactive selection if omitted)');
        Output::option('--force', 'Overwrite existing test files');
        Output::option('--only=<type>', 'Generate only a specific type (unit|feature|database|middleware)');
        Output::option('--dry-run', 'Show what would be generated without creating files');
        Output::newLine();
        echo "  Examples:\n";
        Output::example("php bin/neo {$this->getName()} --project=MyApp");
        Output::example("php bin/neo {$this->getName()} --project=MyApp --only=database --dry-run");
        Output::example("php bin/neo {$this->getName()}");

        return '';
    }
}