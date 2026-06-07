<?php
declare(strict_types=1);

namespace Neo\Core\Application\Tests;

use Neo\Core\Application\ApplicationPaths;
use Neo\Core\DI\Container;
use PHPUnit\Framework\TestCase;

class ApplicationPathsTest extends TestCase
{
    private string $tmpDir;
    private Container $container;

    /** @var array<string, mixed> */
    private array $registered = [];

    protected function setUp(): void
    {
        $this->tmpDir = sys_get_temp_dir() . '/neo_app_paths_' . uniqid();
        mkdir($this->tmpDir, 0777, true);
        mkdir($this->tmpDir . '/public', 0777, true);

        $this->registered = [];

        $this->container = $this->createStub(Container::class);
        $this->container->method('get')->willReturnCallback(
            fn($key) => $this->registered[$key] ?? null
        );
        $this->container->method('set')->willReturnCallback(
            function ($key, $value): void {
                $this->registered[$key] = $value;
            }
        );
    }

    protected function tearDown(): void
    {
        $this->removeDir($this->tmpDir);
    }

    private function removeDir(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }

        foreach (scandir($dir) as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }
            $path = $dir . '/' . $item;
            is_dir($path) ? $this->removeDir($path) : unlink($path);
        }

        rmdir($dir);
    }

    private function makePaths(string $appName): ApplicationPaths
    {
        $this->registered['application'] = $appName;

        return new class($this->container, $this->tmpDir) extends ApplicationPaths {
            public function __construct(Container $container, private string $fakeBase)
            {
                parent::__construct($container);
            }

            public function register(): void
            {
                $appName = $this->container->get('application');
                $basePath = $this->fakeBase;

                $this->container->set('basePath', $basePath);

                $publicPath = $this->resolvePublicPathPublic($basePath);

                $this->container->set('publicPath', $publicPath);
                $this->container->set('buildsPath', $publicPath . '/builds/');
                $this->container->set('srcPath', $basePath . '/src');
                $this->container->set('storagePath', $basePath . '/src/' . $appName . '/Storage');
                $this->container->set('configsPath', $basePath . '/src/' . $appName . '/Config');
                $this->container->set('viewsPath', $basePath . '/src/' . $appName . '/App/Views');
                $this->container->set('controllersPath', $basePath . '/src/' . $appName . '/App/Controllers');
                $this->container->set('assetsPath', $basePath . '/src/' . $appName . '/Assets/');
                $this->container->set('repositoryPath', $basePath . '/src/' . $appName . '/Repository');
                $this->container->set('modelPath', $basePath . '/src/' . $appName . '/Model');
                $this->container->set('formPath', $basePath . '/src/' . $appName . '/App/Forms');
                $this->container->set('listenersPath', $basePath . '/src/' . $appName . '/App/Event/Listener');
                $this->container->set('cronsPath', $basePath . '/src/' . $appName . '/App/Crons');
                $this->container->set('manifestFilename', 'manifest.json');
                $this->container->set('controllerNamespace', 'Neo\\Src\\' . $appName . '\\App\\Controllers\\');
                $this->container->set('modelNamespace', 'Neo\\Src\\' . $appName . '\\Model');
                $this->container->set('repositoryNamespace', 'Neo\\Src\\' . $appName . '\\Repository');
                $this->container->set('formNamespace', 'Neo\\Src\\' . $appName . '\\App\\Forms');

                if (!empty($GLOBALS['_NEO_TEST_CONFIGS_PATH'])) {
                    $this->container->set('testConfigsPath', $GLOBALS['_NEO_TEST_CONFIGS_PATH']);
                }
            }

            public function resolvePublicPathPublic(string $basePath): string
            {
                return $this->resolvePublicPath($basePath);
            }
        };
    }

    public function testResolvesPublicHtmlOverPublic(): void
    {
        mkdir($this->tmpDir . '/public_html', 0777, true);

        $paths = $this->makePaths('TestApp');
        $paths->register();

        $this->assertStringEndsWith('public_html', $this->registered['publicPath']);
    }

    public function testResolvesPublicWhenNoPublicHtml(): void
    {
        $paths = $this->makePaths('TestApp');
        $paths->register();

        $this->assertStringEndsWith('public', $this->registered['publicPath']);
    }

    public function testFallsBackToPublicPathStringWhenNeitherExists(): void
    {
        $this->removeDir($this->tmpDir . '/public');

        $paths = $this->makePaths('TestApp');
        $paths->register();

        $this->assertStringEndsWith('/public', $this->registered['publicPath']);
    }

    public function testRegistersBasePath(): void
    {
        $paths = $this->makePaths('TestApp');
        $paths->register();

        $this->assertSame($this->tmpDir, $this->registered['basePath']);
    }

    public function testRegistersBuildsPath(): void
    {
        $paths = $this->makePaths('TestApp');
        $paths->register();

        $this->assertStringContainsString('/builds/', $this->registered['buildsPath']);
    }

    public function testRegistersSrcPath(): void
    {
        $paths = $this->makePaths('TestApp');
        $paths->register();

        $this->assertSame($this->tmpDir . '/src', $this->registered['srcPath']);
    }

    public function testRegistersAppSpecificPaths(): void
    {
        $paths = $this->makePaths('MyApp');
        $paths->register();

        $this->assertStringContainsString('MyApp', $this->registered['storagePath']);
        $this->assertStringContainsString('MyApp', $this->registered['configsPath']);
        $this->assertStringContainsString('MyApp', $this->registered['viewsPath']);
        $this->assertStringContainsString('MyApp', $this->registered['assetsPath']);
    }

    public function testRegistersNamespaces(): void
    {
        $paths = $this->makePaths('MyApp');
        $paths->register();

        $this->assertStringContainsString('MyApp', $this->registered['controllerNamespace']);
        $this->assertStringContainsString('MyApp', $this->registered['modelNamespace']);
        $this->assertStringContainsString('MyApp', $this->registered['repositoryNamespace']);
        $this->assertStringContainsString('MyApp', $this->registered['formNamespace']);
    }

    public function testRegistersManifestFilename(): void
    {
        $paths = $this->makePaths('MyApp');
        $paths->register();

        $this->assertSame('manifest.json', $this->registered['manifestFilename']);
    }

    public function testAssetsPathEndsWithSlash(): void
    {
        $paths = $this->makePaths('MyApp');
        $paths->register();

        $this->assertStringEndsWith('/', $this->registered['assetsPath']);
    }

    public function testBuildsPathEndsWithSlash(): void
    {
        $paths = $this->makePaths('MyApp');
        $paths->register();

        $this->assertStringEndsWith('/', $this->registered['buildsPath']);
    }

    public function testRegistersTestConfigsPathWhenGlobalSet(): void
    {
        $GLOBALS['_NEO_TEST_CONFIGS_PATH'] = '/tmp/test-configs';

        $paths = $this->makePaths('MyApp');
        $paths->register();

        $this->assertSame('/tmp/test-configs', $this->registered['testConfigsPath']);

        unset($GLOBALS['_NEO_TEST_CONFIGS_PATH']);
    }

    public function testDoesNotRegisterTestConfigsPathWhenGlobalNotSet(): void
    {
        unset($GLOBALS['_NEO_TEST_CONFIGS_PATH']);

        $paths = $this->makePaths('MyApp');
        $paths->register();

        $this->assertArrayNotHasKey('testConfigsPath', $this->registered);
    }
}