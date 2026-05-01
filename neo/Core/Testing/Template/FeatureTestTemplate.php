<?php
declare(strict_types=1);

namespace Neo\Core\Testing\Template;

use Neo\Core\Testing\Context\TestClassContext;
use Neo\Core\Testing\Context\TestMethodContext;

class FeatureTestTemplate
{
    public function render(TestClassContext $ctx, string $testNamespace): string
    {
        $testClassName = $ctx->shortName . 'Test';
        $extends = $ctx->customExtends ?? 'FeatureTestCase';
        $useExtends = $ctx->customExtends ?? 'Neo\\Core\\Testing\\FeatureTestCase';

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

        if (!empty($ctx->methods)) {
            foreach ($ctx->methods as $method) {
                $lines[] = $this->buildFromMethodCtx($method, $ctx->shortName);
            }
            return implode("\n\n", $lines);
        }

        $cases = !empty($ctx->cases) ? $ctx->cases : ['returns_success'];
        foreach ($cases as $case) {
            $lines[] = $this->stub(
                'test_' . $case,
                'GET',
                '/',
                "// TODO: assert {$ctx->shortName} — {$case}"
            );
        }

        return implode("\n\n", $lines);
    }

    private function buildFromMethodCtx(TestMethodContext $method, string $className): string
    {
        $cases = !empty($method->cases) ? $method->cases : ['returns_success'];
        $httpMethod = strtolower($method->httpMethod);
        $route = $method->route ?? '/';
        $lines = [];

        foreach ($cases as $case) {
            $testName = 'test_' . $method->name . '_' . $case;
            $lines[] = $this->stub($testName, $httpMethod, $route, "// TODO: assert {$className}::{$method->name}() — {$case}");
        }

        return implode("\n\n", $lines);
    }

    private function stub(string $name, string $httpMethod, string $route, string $assert): string
    {
        $call = match(strtolower($httpMethod)) {
            'post' => "\$response = \$this->post('{$route}', [/* body */]);",
            'put' => "\$response = \$this->put('{$route}', [/* body */]);",
            'delete' => "\$response = \$this->delete('{$route}');",
            default => "\$response = \$this->get('{$route}');",
        };

        return <<<PHP
    public function {$name}(): void
    {
        {$call}
        {$assert}
        \$this->assertStatus(200, \$response);
    }
PHP;
    }
}