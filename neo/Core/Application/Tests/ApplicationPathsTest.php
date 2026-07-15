<?php

namespace Neo\Core\Application\Tests;

use Neo\Core\Application\ApplicationPaths;
use Neo\Core\DI\Container;
use Neo\Core\DI\Exception\ContainerException;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\NotFoundExceptionInterface;

final class ApplicationPathsTest extends TestCase
{
    private string $tmpDir;
    private bool $hadTestConfigsPathGlobal;
    private mixed $originalTestConfigsPathGlobal;

    protected function setUp(): void
    {
        $this->tmpDir = sys_get_temp_dir() . '/neo-app-paths-test-' . uniqid();
        mkdir($this->tmpDir, 0777, true);

        $this->hadTestConfigsPathGlobal = array_key_exists('_NEO_TEST_CONFIGS_PATH', $GLOBALS);
        $this->originalTestConfigsPathGlobal = $GLOBALS['_NEO_TEST_CONFIGS_PATH'] ?? null;
        unset($GLOBALS['_NEO_TEST_CONFIGS_PATH']);
    }

    protected function tearDown(): void
    {
        $this->deleteDir($this->tmpDir);

        if ($this->hadTestConfigsPathGlobal) {
            $GLOBALS['_NEO_TEST_CONFIGS_PATH'] = $this->originalTestConfigsPathGlobal;
        } else {
            unset($GLOBALS['_NEO_TEST_CONFIGS_PATH']);
        }
    }

    private function deleteDir(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }

        foreach (scandir($dir) as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }
            $path = $dir . '/' . $item;
            is_dir($path) ? $this->deleteDir($path) : unlink($path);
        }

        rmdir($dir);
    }

    public function testResolvePublicPathPrefersPublicHtmlWhenPresent(): void
    {
        mkdir($this->tmpDir . '/public_html');
        mkdir($this->tmpDir . '/public');

        $paths = new TestableApplicationPaths(new Container());

        self::assertSame(
            realpath($this->tmpDir . '/public_html'),
            $paths->exposeResolvePublicPath($this->tmpDir)
        );
    }

    public function testResolvePublicPathFallsBackToPublicWhenPresent(): void
    {
        mkdir($this->tmpDir . '/public');

        $paths = new TestableApplicationPaths(new Container());

        self::assertSame(
            realpath($this->tmpDir . '/public'),
            $paths->exposeResolvePublicPath($this->tmpDir)
        );
    }

    public function testResolvePublicPathDefaultsToPublicWhenNeitherExists(): void
    {
        $paths = new TestableApplicationPaths(new Container());

        self::assertSame(
            $this->tmpDir . '/public',
            $paths->exposeResolvePublicPath($this->tmpDir)
        );
    }

    /**
     * @throws ContainerExceptionInterface
     * @throws ContainerException
     * @throws NotFoundExceptionInterface
     */
    public function testRegisterSetsAllExpectedContainerKeys(): void
    {
        $container = new Container();
        $container->set('application', 'TestApp');

        $paths = new ApplicationPaths($container);
        $paths->register();

        $expectedBasePath = realpath(__DIR__ . '/../../../../');
        self::assertSame($expectedBasePath, $container->get('basePath'));

        self::assertIsString($container->get('publicPath'));
        self::assertSame(
            $container->get('publicPath') . '/builds/',
            $container->get('buildsPath')
        );
        self::assertSame($expectedBasePath . '/src', $container->get('srcPath'));

        self::assertSame($expectedBasePath . '/src/TestApp/Storage', $container->get('storagePath'));
        self::assertSame($expectedBasePath . '/src/TestApp/Config', $container->get('configsPath'));
        self::assertSame($expectedBasePath . '/src/TestApp/Templates', $container->get('viewsPath'));
        self::assertSame($expectedBasePath . '/src/TestApp/App/Controllers', $container->get('controllersPath'));
        self::assertSame($expectedBasePath . '/src/TestApp/Assets/', $container->get('assetsPath'));
        self::assertSame($expectedBasePath . '/src/TestApp/Database/Repository', $container->get('repositoryPath'));
        self::assertSame($expectedBasePath . '/src/TestApp/Database/Model', $container->get('modelPath'));
        self::assertSame($expectedBasePath . '/src/TestApp/Database/Forms', $container->get('formPath'));
        self::assertSame($expectedBasePath . '/src/TestApp/App/Event/Listener', $container->get('listenersPath'));
        self::assertSame($expectedBasePath . '/src/TestApp/App/Crons', $container->get('cronsPath'));

        self::assertSame('manifest.json', $container->get('manifestFilename'));
        self::assertSame('Neo\\Src\\TestApp\\App\\Controllers\\', $container->get('controllerNamespace'));
        self::assertSame('Neo\\Src\\TestApp\\Database\\Model', $container->get('modelNamespace'));
        self::assertSame('Neo\\Src\\TestApp\\Database\\Repository', $container->get('repositoryNamespace'));
        self::assertSame('Neo\\Src\\TestApp\\Database\\Forms', $container->get('formNamespace'));
    }

    /**
     * @throws ContainerExceptionInterface
     * @throws NotFoundExceptionInterface
     */
    public function testRegisterDoesNotSetTestConfigsPathWhenGlobalIsEmpty(): void
    {
        $container = new Container();
        $container->set('application', 'TestApp');

        new ApplicationPaths($container)->register();

        self::assertFalse($container->has('testConfigsPath'));
    }

    /**
     * @throws ContainerExceptionInterface
     * @throws NotFoundExceptionInterface
     * @throws ContainerException
     */
    public function testRegisterSetsTestConfigsPathWhenGlobalIsSet(): void
    {
        $GLOBALS['_NEO_TEST_CONFIGS_PATH'] = $this->tmpDir . '/test-configs';

        $container = new Container();
        $container->set('application', 'TestApp');

        new ApplicationPaths($container)->register();

        self::assertSame($this->tmpDir . '/test-configs', $container->get('testConfigsPath'));
    }
}