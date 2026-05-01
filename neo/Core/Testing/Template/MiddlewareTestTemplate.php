<?php
declare(strict_types=1);

namespace Neo\Core\Testing\Template;

use Neo\Core\Testing\Context\TestClassContext;

class MiddlewareTestTemplate
{
    public function render(TestClassContext $ctx, string $testNamespace): string
    {
        $testClassName = $ctx->shortName . 'Test';
        $extends = $ctx->customExtends ?? 'MiddlewareTestCase';
        $useExtends = $ctx->customExtends ?? 'Neo\\Core\\Testing\\MiddlewareTestCase';

        $methods = $this->buildMethods($ctx);

        return <<<PHP
<?php
declare(strict_types=1);

namespace {$testNamespace};

use {$ctx->fqcn};
use {$useExtends};

class {$testClassName} extends {$extends}
{

{$methods}
}
PHP;
    }

    private function buildMethods(TestClassContext $ctx): string
    {
        $lines = [];

        $cases = !empty($ctx->cases)
            ? $ctx->cases
            : ['passes', 'blocks'];

        foreach ($cases as $case) {
            $body = match($case) {
                'passes' => <<<PHP
        \$middleware = \$this->makeMiddleware({$ctx->shortName}::class);
        \$this->assertMiddlewarePasses(\$middleware);
PHP,
                'blocks' => <<<PHP
        \$middleware = \$this->makeMiddleware({$ctx->shortName}::class);
        \$this->assertMiddlewareBlocks(\$middleware);
PHP,
                default => <<<PHP
        \$middleware = \$this->makeMiddleware({$ctx->shortName}::class);
        // TODO: assert {$case}
        \$this->assertTrue(true);
PHP,
            };

            $lines[] = <<<PHP
    public function test_{$case}(): void
    {
{$body}
    }
PHP;
        }

        if (!empty($ctx->methods)) {
            foreach ($ctx->methods as $method) {
                $methodCases = !empty($method->cases) ? $method->cases : ['it_works'];
                foreach ($methodCases as $case) {
                    $testName = 'test_' . $method->name . '_' . $case;
                    $lines[]  = <<<PHP
    public function {$testName}(): void
    {
        \$middleware = \$this->makeMiddleware({$ctx->shortName}::class);
        // TODO: test {$method->name}() — {$case}
        \$this->assertTrue(true);
    }
PHP;
                }
            }
        }

        return implode("\n\n", $lines);
    }
}