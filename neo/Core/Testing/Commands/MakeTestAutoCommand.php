<?php
declare(strict_types=1);

namespace Neo\Core\Testing\Commands;

use Neo\Core\Console\Abstract\AbstractCommand;
use Neo\Core\Console\Attribute\Command;
use Neo\Core\Console\Enum\ExitCode;
use Neo\Core\Console\Input\Input;
use Neo\Core\Console\Input\InputOption;
use Neo\Core\Console\Output\Output;
use Neo\Core\DI\Container;
use Neo\Core\Testing\Generator\TestGenerator;
use Neo\Core\Testing\Scaffold\TestScaffolder;

#[Command(
    name: 'make:test:auto',
    description: 'Auto-generate test files from #[Test] attributes',
    category: 'Testing',
)]
final class MakeTestAutoCommand extends AbstractCommand
{
    public function __construct(
        private readonly Container $container
    ) {}

    public function configure(): void
    {
        $this->addOption(
            name: 'project',
            shortcut: null,
            mode: InputOption::REQUIRED,
            description: 'Target project',
        );

        $this->addOption(
            name: 'force',
            shortcut: null,
            mode: InputOption::NONE,
            description: 'Overwrite test files',
        );

        $this->addOption(
            name: 'only',
            shortcut: null,
            mode: InputOption::REQUIRED,
            description: 'Only generate unit, feature, database, or middleware',
        );

        $this->addOption(
            name: 'dry-run',
            shortcut: null,
            mode: InputOption::NONE,
            description: 'Show result without writing files',
        );
    }

    public function do(Input $input, Output $output): ExitCode
    {
        $project = $input->getOption('project') ?? Input::choice('Target project ?', $this->getAvailableProjects());
        $force = (bool) $input->getOption('force');
        $onlyType = $input->getOption('only');
        $dryRun = (bool) $input->getOption('dry-run');

        $basePath = ROOT_DIR . "/src/$project";
        if (!is_dir($basePath)) {
            Output::error("Project '$project' not found.");
            return ExitCode::FAILURE;
        }

        (new TestScaffolder())->ensure($basePath, $project);

        Output::title("Scanning #[Test] attributes in '$project'");

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
            Output::warning('No #[Test] attributes found.');
        }

        Output::separator();
        Output::success('Done.');

        return ExitCode::SUCCESS;
    }

    protected function getAvailableProjects(): array
    {
        return array_map('basename', glob(ROOT_DIR . '/src/*', GLOB_ONLYDIR));
    }
}