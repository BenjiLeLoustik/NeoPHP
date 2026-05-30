<?php
declare(strict_types=1);

namespace Neo\Core\Testing\Commands;

use Neo\Core\Console\Attribute\Command;
use Neo\Core\Console\Helper\Args;
use Neo\Core\Console\Helper\Output;
use Neo\Core\Console\Interface\CommandInterface;
use Neo\Core\Testing\Scaffold\TestScaffolder;

#[Command(
    name: 'make:test',
    description: 'Generate a PHPUnit test skeleton for a project'
)]
final class MakeTestCommand implements CommandInterface
{
    private const VALID_TYPES = ['unit', 'feature', 'database', 'middleware'];

    public function execute(array $args): void
    {
        $testName = Args::positional($args, 0);
        $project = Args::option($args, '--project');
        $type = strtolower(Args::option($args, '--type') ?? 'unit');
        $force = Args::flag($args, '--force');

        if (!$testName || !$project) {
            Output::error('Missing arguments.');
            Output::muted('Usage: php bin/neo make:test <TestName> --type=<type> --project=<name>');
            return;
        }

        if (!in_array($type, self::VALID_TYPES, true)) {
            Output::error("Invalid type '$type'. Valid: " . implode(', ', self::VALID_TYPES));
            return;
        }

        $basePath = ROOT_DIR . "/src/$project";

        if (!is_dir($basePath)) {
            Output::error("Project '$project' does not exist inside ./src/");
            return;
        }

        $testName = $this->normalizeTestName($testName);

        (new TestScaffolder())->ensure($basePath, $project);
        $this->generateTest($basePath, $project, $testName, $type, $force);
    }

    private function generateTest(
        string $basePath,
        string $project,
        string $testName,
        string $type,
        bool $force
    ): void {
        $typeDir = ucfirst($type);
        $targetDir = "$basePath/Tests/$typeDir";

        if (!is_dir($targetDir)) {
            mkdir($targetDir, 0777, true);
        }

        $filePath = "$targetDir/{$testName}.php";

        if (file_exists($filePath) && !$force) {
            Output::warning("Test '$testName' already exists. Use --force to overwrite.");
            return;
        }

        $namespace = "Neo\\Src\\$project\\Tests\\$typeDir";
        $content = $this->buildTestContent($namespace, $testName, $type, $project);

        file_put_contents($filePath, $content);
        Output::success("Test '$testName' generated: src/$project/Tests/$typeDir/{$testName}.php");
    }

    private function buildTestContent(string $namespace, string $testName, string $type, string $project): string
    {
        return match ($type) {
            'unit' => $this->unitTemplate($namespace, $testName, $project),
            'feature' => $this->featureTemplate($namespace, $testName, $project),
            'database' => $this->databaseTemplate($namespace, $testName, $project),
            'middleware' => $this->middlewareTemplate($namespace, $testName, $project),
            default => $this->unitTemplate($namespace, $testName, $project),
        };
    }

    private function unitTemplate(string $namespace, string $testName, string $project): string
    {
        return <<<PHP
<?php
declare(strict_types=1);

namespace {$namespace};

use Neo\Core\Testing\TestCase;

/**
 * Unit test: {$testName}
 * Tests an isolated PHP class (Service, Model, Util…).
 *
 * - \$this->get(ServiceClass::class)    → resolve from container
 * - \$this->swap(ServiceClass::class, \$mock) → replace with mock
 */
class {$testName} extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
    }

    public function test_example(): void
    {
        \$this->assertTrue(true);
    }
}
PHP;
    }

    private function featureTemplate(string $namespace, string $testName, string $project): string
    {
        return <<<PHP
<?php
declare(strict_types=1);

namespace {$namespace};

use Neo\Core\Testing\FeatureTestCase;

/**
 * Feature test: {$testName}
 * Tests an HTTP route end-to-end.
 *
 * - \$this->get('/path')             → send GET request
 * - \$this->post('/path', \$data)     → send POST request
 * - assertStatus(), assertSeeText(), assertJsonKey()
 */
class {$testName} extends FeatureTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
    }

    public function test_page_returns_200(): void
    {
        \$this->assertTrue(true);
    }
}
PHP;
    }

    private function databaseTemplate(string $namespace, string $testName, string $project): string
    {
        return <<<PHP
<?php
declare(strict_types=1);

namespace {$namespace};

use Neo\Core\Testing\DatabaseTestCase;

/**
 * Database test: {$testName}
 * Each test runs inside an auto-rolled-back transaction.
 *
 * - \$this->insertFixture(table, data) → insert a row, returns ID
 * - \$this->fetchAll(table, where)     → fetch rows
 * - \$this->assertDatabaseHas(table, data)
 * - \$this->assertDatabaseMissing(table, data)
 */
class {$testName} extends DatabaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
    }

    public function test_insert_and_retrieve(): void
    {
        \$this->assertTrue(true);
    }
}
PHP;
    }

    private function middlewareTemplate(string $namespace, string $testName, string $project): string
    {
        return <<<PHP
<?php
declare(strict_types=1);

namespace {$namespace};

use Neo\Core\Testing\MiddlewareTestCase;

/**
 * Middleware test: {$testName}
 *
 * - \$this->makeMiddleware(MiddlewareClass::class)
 * - \$this->assertMiddlewarePasses(\$middleware)
 * - \$this->assertMiddlewareBlocks(\$middleware)
 * - \$this->assertMiddlewareBlocksWithCode(\$middleware, 403)
 * - \$this->swap(ServiceClass::class, \$mock)
 */
class {$testName} extends MiddlewareTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
    }

    public function test_middleware_passes(): void
    {
        \$this->assertTrue(true);
    }

    public function test_middleware_blocks(): void
    {
        \$this->assertTrue(true);
    }
}
PHP;
    }

    private function normalizeTestName(string $input): string
    {
        $input = preg_replace('/[^a-zA-Z0-9]+/', ' ', $input);
        $input = str_replace(' ', '', ucwords($input));

        if (!str_ends_with($input, 'Test')) {
            $input .= 'Test';
        }

        return $input;
    }

    public function getName(): string
    {
        return 'make:test';
    }

    public function getDescription(): string
    {
        return 'Generate a PHPUnit test skeleton for a project';
    }

    public function getHelp(): string
    {
        Output::usage('make:test', $this->getDescription());
        Output::option('<TestName>',        'Test class name (e.g. UserServiceTest)');
        Output::option('--project=<name>',  'Target project inside ./src/');
        Output::option('--type=unit',       'Isolated class test (service, model…)');
        Output::option('--type=feature',    'End-to-end HTTP route test');
        Output::option('--type=database',   'Repository test with auto-rollback');
        Output::option('--type=middleware', 'Middleware pass/block test');
        Output::option('--force',           'Overwrite existing file');
        Output::newLine();
        echo "  Examples:\n";
        Output::example('php bin/neo make:test UserServiceTest --type=unit --project=Blog');
        Output::example('php bin/neo make:test UserControllerTest --type=feature --project=Blog');

        return '';
    }
}