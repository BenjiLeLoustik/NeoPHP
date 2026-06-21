<?php
declare(strict_types=1);

namespace Neo\Core\Controller\Tests;

use Neo\Core\Controller\Exception\AbstractControllerException;
use Neo\Core\Controller\Tests\Fixture\ConcreteTestController;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

final class AbstractControllerTest extends TestCase
{
    private string $tmpDir;

    protected function setUp(): void
    {
        $this->tmpDir = sys_get_temp_dir() . '/neo-controller-test-' . uniqid();
        mkdir($this->tmpDir, 0777, true);
    }

    protected function tearDown(): void
    {
        foreach (glob($this->tmpDir . '/*') ?: [] as $file) {
            unlink($file);
        }
        rmdir($this->tmpDir);
    }

    public function testConstructorWithNullContainerDoesNotThrow(): void
    {
        $controller = new ConcreteTestController(null);

        self::assertInstanceOf(ConcreteTestController::class, $controller);
    }

    /**
     * @throws AbstractControllerException
     */
    public function testRegisteredMethodIsInvokedWithForwardedArguments(): void
    {
        $controller = new ConcreteTestController(null);

        $controller->registerMethod('add', fn(int $a, int $b) => $a + $b);

        self::assertSame(7, $controller->__call('add', [3, 4]));
    }

    public function testRegisteredMethodCanCloseOverState(): void
    {
        $controller = new ConcreteTestController(null);
        $calls = [];

        $controller->registerMethod('track', function (string $event) use (&$calls) {
            $calls[] = $event;
            return count($calls);
        });

        self::assertSame(1, $controller->track('first'));
        self::assertSame(2, $controller->track('second'));
        self::assertSame(['first', 'second'], $calls);
    }

    public function testCallingUnregisteredMethodThrowsAbstractControllerException(): void
    {
        $controller = new ConcreteTestController(null);

        try {
            $controller->doStuff();
            self::fail('Expected AbstractControllerException was not thrown.');
        } catch (AbstractControllerException $e) {
            self::assertSame(
                "Method 'doStuff' is not registered on this controller.",
                $e->getMessage()
            );
        }
    }

    /**
     * @throws \ReflectionException
     */
    private function invokeResolveFqcn(string $fileContent): ?string
    {
        $file = $this->tmpDir . '/' . uniqid() . '.php';
        file_put_contents($file, $fileContent);

        $controller = new ConcreteTestController(null);

        $method = new ReflectionMethod($controller, 'resolveFqcn');

        return $method->invoke($controller, $file);
    }

    public function testResolveFqcnExtractsNamespaceAndClassName(): void
    {
        $content = <<<PHP
<?php
declare(strict_types=1);

namespace Neo\Core\Event;

class EventControllerExtension
{
}
PHP;

        self::assertSame(
            'Neo\Core\Event\EventControllerExtension',
            $this->invokeResolveFqcn($content)
        );
    }

    public function testResolveFqcnHandlesAbstractKeywordBeforeClass(): void
    {
        $content = <<<PHP
<?php

namespace Neo\Core\Foo;

abstract class FooControllerExtension
{
}
PHP;

        self::assertSame(
            'Neo\Core\Foo\FooControllerExtension',
            $this->invokeResolveFqcn($content)
        );
    }

    public function testResolveFqcnReturnsClassNameOnlyWhenNamespaceIsMissing(): void
    {
        $content = <<<PHP
<?php

class NoNamespaceExtension
{
}
PHP;

        self::assertSame('NoNamespaceExtension', $this->invokeResolveFqcn($content));
    }

    public function testResolveFqcnReturnsNullWhenNoClassDeclarationFound(): void
    {
        $content = <<<PHP
<?php

namespace Neo\Core\Empty;

PHP;

        self::assertNull($this->invokeResolveFqcn($content));
    }
}