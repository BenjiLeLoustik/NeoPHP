<?php
declare(strict_types=1);

namespace Neo\Core\Testing\Template;

use Neo\Core\Testing\Context\TestClassContext;

class DatabaseTestTemplate
{
    public function render(TestClassContext $ctx, string $testNamespace): string
    {
        $testClassName = $ctx->shortName . 'Test';
        $extends = $ctx->customExtends ?? 'DatabaseTestCase';
        $useExtends = $ctx->customExtends ?? 'Neo\\Core\\Testing\\DatabaseTestCase';

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

        if (!empty($ctx->methods)) {
            foreach ($ctx->methods as $method) {
                $cases = !empty($method->cases) ? $method->cases : ['nominal'];
                foreach ($cases as $case) {
                    $testName = 'test_' . $method->name . '_' . $case;
                    $fixture = $this->buildFixture($ctx->dataset);
                    $lines[] = $this->stub($testName, $fixture, "// TODO: assert {$method->name}() — {$case}");
                }
            }
            return implode("\n\n", $lines);
        }

        $defaultMethods = ['find_by_id', 'find_all', 'save', 'delete'];
        $cases = !empty($ctx->cases) ? $ctx->cases : $defaultMethods;

        foreach ($cases as $case) {
            $fixture = $this->buildFixture($ctx->dataset);
            $lines[] = $this->stub('test_' . $case, $fixture, "// TODO: assert {$case}");
        }

        return implode("\n\n", $lines);
    }

    private function buildFixture(array $dataset): string
    {
        if (empty($dataset)) {
            return '// TODO: insert fixture via $this->insertFixture(\'table\', [...])';
        }

        $table = $dataset['table'] ?? 'table';
        $data = $dataset['data']  ?? [];
        $export = var_export($data, true);

        return "\$this->insertFixture('{$table}', {$export});";
    }

    private function stub(string $name, string $fixture, string $assert): string
    {
        return <<<PHP
    public function {$name}(): void
    {
        {$fixture}
        {$assert}
        \$this->assertTrue(true);
    }
PHP;
    }
}