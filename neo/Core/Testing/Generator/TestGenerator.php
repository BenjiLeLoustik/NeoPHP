<?php
declare(strict_types=1);

namespace Neo\Core\Testing\Generator;

use Neo\Core\DI\Container;
use Neo\Core\DI\Exception\ContainerException;
use Neo\Core\Error\Exception\FrameworkException;
use Neo\Core\Testing\Context\TestClassContext;
use Neo\Core\Testing\Enum\TestType;
use Neo\Core\Testing\Exception\TestingException;
use Neo\Core\Testing\Scanner\TestScanner;
use Neo\Core\Testing\Template\DatabaseTestTemplate;
use Neo\Core\Testing\Template\FeatureTestTemplate;
use Neo\Core\Testing\Template\MiddlewareTestTemplate;
use Neo\Core\Testing\Template\ModelTestTemplate;
use Neo\Core\Testing\Template\UnitTestTemplate;

class TestGenerator
{
    private string $appName;
    private string $srcPath;
    private string $testsPath;
    private string $testNamespace;

    /**
     * @throws \ReflectionException
     * @throws ContainerException
     */
    public function __construct(
        private Container $container
    ) {
        $this->appName = $this->container->get('application');
        $this->srcPath = $this->container->get('srcPath') . '/' . $this->appName;
        $this->testsPath = $this->srcPath . '/Tests';
        $this->testNamespace = 'Neo\\Src\\' . $this->appName . '\\Tests';
    }

    /**
     * @return array{generated: array<int, string>, skipped: array<int, string>}
     * @throws TestingException
     */
    public function generate(
        bool $force = false,
        ?string $onlyType  = null,
        bool $dryRun = false,
    ): array {
        $scanner = new TestScanner();
        $contexts = $scanner->scan($this->srcPath);

        $generated = [];
        $skipped = [];

        foreach ($contexts as $ctx) {
            if ($ctx->skip) {
                $skipped[] = $ctx->shortName;
                continue;
            }

            if ($onlyType !== null && $ctx->type->value !== $onlyType) {
                continue;
            }

            $subDir = $ctx->type->subDir();
            $targetDir = $this->testsPath . '/' . $subDir;
            $file = $targetDir . '/' . $ctx->shortName . 'Test.php';

            if (!$force && file_exists($file)) {
                $skipped[] = $ctx->shortName . 'Test (already exists)';
                continue;
            }

            $content = $this->renderTemplate($ctx);

            if ($dryRun) {
                $generated[] = '[dry-run] ' . str_replace($this->srcPath . '/', '', $file);
                continue;
            }

            if (!is_dir($targetDir) && !mkdir($targetDir, 0777, true) && !is_dir($targetDir)) {
                throw new TestingException(
                    title: 'Test Generator Error',
                    message: sprintf("Cannot create directory '%s'.", $targetDir),
                    code: 500
                );
            }

            if (file_put_contents($file, $content) === false) {
                throw new TestingException(
                    title: 'Test Generator Error',
                    message: sprintf("Cannot write test file '%s'.", $file),
                    code: 500
                );
            }

            $generated[] = str_replace($this->srcPath . '/', '', $file);
        }

        return [
            'generated' => $generated,
            'skipped' => $skipped
        ];
    }

    private function renderTemplate(TestClassContext $ctx): string
    {
        $subNamespace = $ctx->type->subDir();
        $testNamespace = $this->testNamespace . '\\' . $subNamespace;

        $isModel = str_contains($ctx->namespace, 'Model')
            && !str_contains($ctx->fqcn, 'Repository')
            && !str_contains($ctx->fqcn, 'Controller');

        return match(true) {
            $isModel => new ModelTestTemplate()->render($ctx, $testNamespace),
            $ctx->type === TestType::Database => new DatabaseTestTemplate()->render($ctx, $testNamespace),
            $ctx->type === TestType::Feature => new FeatureTestTemplate()->render($ctx, $testNamespace),
            $ctx->type === TestType::Middleware => new MiddlewareTestTemplate()->render($ctx, $testNamespace),
            default => new UnitTestTemplate()->render($ctx, $testNamespace),
        };
    }
}