<?php
declare(strict_types=1);

namespace Neo\Core\Testing\Template;

use Neo\Core\Testing\Context\TestClassContext;
use Neo\Core\Testing\Context\TestMethodContext;

class UnitTestTemplate
{
    public function render(TestClassContext $ctx, string $testNamespace): string
    {
        $testClassName = $ctx->shortName . 'Test';
        $extends = $ctx->customExtends ?? 'TestCase';
        $useExtends = $ctx->customExtends ?? 'Neo\\Core\\Testing\\TestCase';

        $methods = $this->buildMethods($ctx);

        return <<<PHP
<?php
declare(strict_types=1);

namespace {$testNamespace};

use {$ctx->fqcn};
use {$useExtends};

class {$testClassName} extends {$extends}
{
    private {$ctx->shortName} \$subject;

    protected function setUp(): void
    {
        parent::setUp();
        \$this->subject = \$this->get({$ctx->shortName}::class);
    }

{$methods}
}
PHP;
    }

    private function buildMethods(TestClassContext $ctx): string
    {
        $lines = [];

        $cases = !empty($ctx->cases)
            ? $ctx->cases
            : ['it_works'];

        if (!empty($ctx->methods)) {
            foreach ($ctx->methods as $method) {
                $methodCases = !empty($method->cases) ? $method->cases : ['it_works'];
                foreach ($methodCases as $case) {
                    $testName = 'test_' . $method->name . '_' . $case;
                    $lines[]  = $this->stub($testName, "// TODO: test {$ctx->shortName}::{$method->name}() — {$case}");
                }
            }
            return implode("\n\n", $lines);
        }

        foreach ($cases as $case) {
            $lines[] = $this->stub('test_' . $case, "// TODO: test {$ctx->shortName} — {$case}");
        }

        return implode("\n\n", $lines);
    }

    private function stub(string $name, string $body): string
    {
        return <<<PHP
    public function {$name}(): void
    {
        {$body}
        \$this->assertTrue(true);
    }
PHP;
    }
}