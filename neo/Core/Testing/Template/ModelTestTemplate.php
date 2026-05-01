<?php
declare(strict_types=1);

namespace Neo\Core\Testing\Template;

use Neo\Core\Testing\Context\TestClassContext;
use Neo\Core\Validator\Constraint;
use ReflectionClass;

class ModelTestTemplate
{
    public function render(TestClassContext $ctx, string $testNamespace): string
    {
        $testClassName = $ctx->shortName . 'Test';
        $extends = 'TestCase';
        $useExtends = 'Neo\\Core\\Testing\\TestCase';

        $methods = $this->buildFromConstraints($ctx);

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

    private function buildFromConstraints(TestClassContext $ctx): string
    {
        $lines = [];

        try {
            $refClass = new ReflectionClass($ctx->fqcn);
        } catch (\ReflectionException) {
            return "    // Unable to reflect {$ctx->shortName}";
        }

        $lines[] = <<<PHP
    public function test_can_instantiate(): void
    {
        \$model = new {$ctx->shortName}();
        \$this->assertInstanceOf({$ctx->shortName}::class, \$model);
    }
PHP;

        foreach ($refClass->getProperties() as $prop) {
            $constraints = $prop->getAttributes(Constraint::class, \ReflectionAttribute::IS_INSTANCEOF);

            if (empty($constraints)) continue;

            $propName = $prop->getName();

            foreach ($constraints as $attrRef) {
                $constraintShort = (new \ReflectionClass($attrRef->getName()))->getShortName();
                $testName = 'test_' . $propName . '_fails_' . strtolower($constraintShort);

                $lines[] = <<<PHP
    public function {$testName}(): void
    {
        // Constraint: {$constraintShort} on \${$propName}
        \$model = new {$ctx->shortName}();
        // TODO: set invalid value for \${$propName} and assert validation fails
        \$this->assertTrue(true);
    }
PHP;
            }
        }

        return implode("\n\n", $lines);
    }
}