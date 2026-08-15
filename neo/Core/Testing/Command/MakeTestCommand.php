<?php
declare(strict_types=1);

namespace Neo\Core\Testing\Command;

use Neo\Core\Console\Abstract\AbstractCommand;
use Neo\Core\Console\Attribute\Command;
use Neo\Core\Console\Enum\ExitCode;
use Neo\Core\Console\Input\Input;
use Neo\Core\Console\Input\InputArgument;
use Neo\Core\Console\Input\InputOption;
use Neo\Core\Console\Output\Output;
use Neo\Core\Testing\Scaffold\TestScaffolder;

#[Command(
    name: 'make:test',
    description: 'Generate a PHPUnit test skeleton for a project',
    category: 'Testing',
)]
final class MakeTestCommand extends AbstractCommand
{
    private const array VALID_TYPES = ['unit', 'feature', 'database', 'middleware'];

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
            name: 'type',
            shortcut: null,
            mode: InputOption::REQUIRED,
            description: 'Test type (unit, feature, database, middleware)',
        );

        $this->addOption(
            name: 'force',
            shortcut: null,
            mode: InputOption::NONE,
            description: 'Overwrite file',
        );
    }

    public function do(Input $input, Output $output): ExitCode
    {
        $testName = $input->getArgument('testName') ?? Input::ask('Test class name ?');
        $project = $input->getOption('project') ?? Input::choice('Target project ?', $this->getAvailableProjects());
        $type = strtolower($input->getOption('type') ?? '');
        $force = (bool) $input->getOption('force');

        if (!in_array($type, self::VALID_TYPES, true)) {
            $type = Input::choice('Test type ?', self::VALID_TYPES, 'unit');
        }

        $basePath = ROOT_DIR . "/src/$project";
        if (!is_dir($basePath)) {
            Output::error("Project '$project' not found.");
            return ExitCode::FAILURE;
        }

        if (!$testName) {
            Output::error('Test class name is required.');
            return ExitCode::INVALID;
        }

        $testName = $this->normalizeTestName($testName);
        new TestScaffolder()->ensure($basePath, $project);

        $this->generateTest($basePath, $project, $testName, $type, $force);

        return ExitCode::SUCCESS;
    }

    private function generateTest(string $base, string $proj, string $name, string $type, bool $force): void
    {
        $typeDir = ucfirst($type);
        $targetDir = "$base/Tests/$typeDir";
        if (!is_dir($targetDir)) mkdir($targetDir, 0777, true);

        $filePath = "$targetDir/{$name}.php";
        if (file_exists($filePath) && !$force) {
            Output::warning("Test '$name' already exists.");
            return;
        }

        $ns = "Neo\\Src\\$proj\\Tests\\$typeDir";
        file_put_contents($filePath, $this->buildTemplate($ns, $name, $type));
        Output::success("Test generated: $filePath");
    }

    private function buildTemplate(string $ns, string $name, string $type): string
    {
        $base = "<?php\ndeclare(strict_types=1);\n\nnamespace $ns;\n\n";
        return match ($type) {
            'feature' => $base . "use Neo\\Core\\Testing\\FeatureTestCase;\n\nclass $name extends FeatureTestCase\n{\n    public function test_example(): void\n    {\n        \$this->assertTrue(true);\n    }\n}",
            'database' => $base . "use Neo\\Core\\Testing\\DatabaseTestCase;\n\nclass $name extends DatabaseTestCase\n{\n    public function test_example(): void\n    {\n        \$this->assertTrue(true);\n    }\n}",
            'middleware' => $base . "use Neo\\Core\\Testing\\MiddlewareTestCase;\n\nclass $name extends MiddlewareTestCase\n{\n    public function test_example(): void\n    {\n        \$this->assertTrue(true);\n    }\n}",
            default => $base . "use Neo\\Core\\Testing\\TestCase;\n\nclass $name extends TestCase\n{\n    public function test_example(): void\n    {\n        \$this->assertTrue(true);\n    }\n}",
        };
    }

    private function normalizeTestName(string $input): string
    {
        $input = preg_replace('/[^a-zA-Z0-9]+/', ' ', $input)
                |> ucwords(...)
                |> (fn($x) => str_replace(' ', '', $x));

        return str_ends_with($input, 'Test') ? $input : $input . 'Test';
    }

    protected function getAvailableProjects(): array
    {
        return glob(ROOT_DIR . '/src/*', GLOB_ONLYDIR)
                |> (fn (array $d): array => array_map(basename(...), $d));
    }
}