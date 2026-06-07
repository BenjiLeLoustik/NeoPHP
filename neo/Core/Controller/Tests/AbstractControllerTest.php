<?php
declare(strict_types=1);

namespace Neo\Core\Controller\Tests;

use Neo\Core\Controller\AbstractController;
use Neo\Core\Controller\Exception\AbstractControllerException;
use Neo\Core\DI\Container;
use PHPUnit\Framework\TestCase;

class AbstractControllerTest extends TestCase
{
    private function makeController(?Container $container = null): AbstractController
    {
        return new class ($container) extends AbstractController {};
    }

    public function testInstantiatesWithoutContainer(): void
    {
        $this->assertInstanceOf(AbstractController::class, $this->makeController(null));
    }

    public function testInstantiatesWithContainer(): void
    {
        $this->assertInstanceOf(AbstractController::class, $this->makeController($this->createStub(Container::class)));
    }

    public function testRegisteredMethodIsCallable(): void
    {
        $controller = $this->makeController(null);
        $controller->registerMethod('greet', fn (string $name): string => "Hello, $name!");

        $this->assertSame('Hello, World!', $controller->__call('greet', ['World']));
    }

    public function testRegisteredMethodReceivesMultipleArguments(): void
    {
        $controller = $this->makeController(null);
        $controller->registerMethod('add', fn (int $a, int $b): int => $a + $b);

        $this->assertSame(7, $controller->__call('add', [3, 4]));
    }

    public function testRegisteredMethodCanBeOverwritten(): void
    {
        $controller = $this->makeController(null);
        $controller->registerMethod('ping', fn (): string => 'v1');
        $controller->registerMethod('ping', fn (): string => 'v2');

        $this->assertSame('v2', $controller->__call('ping', []));
    }

    public function testRegisteredMethodReturnsNull(): void
    {
        $controller = $this->makeController(null);
        $controller->registerMethod('noop', fn (): mixed => null);

        $this->assertNull($controller->__call('noop', []));
    }

    public function testCallOnUnregisteredMethodThrowsAbstractControllerException(): void
    {
        $this->expectException(AbstractControllerException::class);

        $this->makeController(null)->__call('unknownMethod', []);
    }

    public function testCallExceptionContainsMethodName(): void
    {
        try {
            $this->makeController(null)->__call('missingAction', []);
            $this->fail('Expected AbstractControllerException was not thrown.');
        } catch (AbstractControllerException $e) {
            $this->assertStringContainsString('missingAction', $e->getMessage());
        }
    }

    public function testCallExceptionHasCode500(): void
    {
        try {
            $this->makeController(null)->__call('anyMethod', []);
            $this->fail('Expected AbstractControllerException was not thrown.');
        } catch (AbstractControllerException $e) {
            $this->assertSame(500, $e->getCode());
        }
    }

    public function testDiscoverExtensionsCallsExtendOnValidExtension(): void
    {
        $tmpDir = sys_get_temp_dir() . '/neo_ctrl_ext_' . uniqid();
        $ctrlDir = $tmpDir . '/Controller';
        mkdir($ctrlDir, 0777, true);

        $extClass = 'DummyControllerExtension_' . uniqid('', false);
        $extCode = <<<PHP
<?php
declare(strict_types=1);

use Neo\Core\Controller\AbstractController;
use Neo\Core\Controller\Interface\ControllerExtensionInterface;
use Neo\Core\DI\Container;

final class {$extClass} implements ControllerExtensionInterface
{
    public function extend(AbstractController \$controller, Container \$container): void
    {
        \$controller->registerMethod('fromExtension', fn(): string => 'extended');
    }
}
PHP;

        $extFile = $ctrlDir . "/{$extClass}ControllerExtension.php";
        file_put_contents($extFile, $extCode);

        $container = $this->createStub(Container::class);
        $controller = new class ($container, $tmpDir . '/') extends AbstractController
        {
            public function __construct(?Container $container, string $scanRoot)
            {
                if ($container === null) return;
                $this->container = $container;
                $this->discoverFrom($scanRoot);
            }

            public function discoverFrom(string $root): void
            {
                $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($root));

                foreach ($iterator as $file) {
                    if (!$file->isFile() || $file->getExtension() !== 'php') continue;
                    if (!str_ends_with($file->getFilename(), 'ControllerExtension.php')) continue;

                    $src = file_get_contents($file->getRealPath());
                    if ($src === false) continue;

                    $namespace = '';
                    if (preg_match('/namespace\s+([^;]+);/i', $src, $m)) {
                        $namespace = trim($m[1]);
                    }
                    if (!preg_match('/class\s+([A-Za-z0-9_]+)/i', $src, $m)) continue;
                    $fqcn = $namespace !== '' ? $namespace . '\\' . trim($m[1]) : trim($m[1]);

                    require_once $file->getRealPath();
                    if (!class_exists($fqcn)) continue;

                    $ref = new \ReflectionClass($fqcn);
                    if ($ref->isAbstract() || !$ref->implementsInterface(\Neo\Core\Controller\Interface\ControllerExtensionInterface::class)) continue;

                    /** @var \Neo\Core\Controller\Interface\ControllerExtensionInterface $ext */
                    $ext = new $fqcn();
                    $ext->extend($this, $this->container);
                }
            }
        };

        $this->assertSame('extended', $controller->__call('fromExtension', []));

        unlink($extFile);
        rmdir($ctrlDir);
        rmdir($tmpDir);
    }

    public function testDiscoverExtensionsIgnoresAbstractClasses(): void
    {
        $tmpDir = sys_get_temp_dir() . '/neo_ctrl_ext_abstract_' . uniqid();
        $ctrlDir = $tmpDir . '/Controller';
        mkdir($ctrlDir, 0777, true);

        $extClass = 'AbstractDummyControllerExtension_' . uniqid('', false);
        $extCode = <<<PHP
<?php
use Neo\Core\Controller\AbstractController;
use Neo\Core\Controller\Interface\ControllerExtensionInterface;
use Neo\Core\DI\Container;

abstract class {$extClass} implements ControllerExtensionInterface
{
    public function extend(AbstractController \$controller, Container \$container): void
    {
        \$controller->registerMethod('shouldNotExist', fn(): string => 'boom');
    }
}
PHP;

        file_put_contents($ctrlDir . "/{$extClass}ControllerExtension.php", $extCode);

        $this->expectException(AbstractControllerException::class);

        $this->makeController($this->createStub(Container::class))->__call('shouldNotExist', []);

        array_map('unlink', glob($ctrlDir . '/*.php') ?: []);
        rmdir($ctrlDir);
        rmdir($tmpDir);
    }
}