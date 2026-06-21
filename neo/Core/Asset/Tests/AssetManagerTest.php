<?php

namespace Neo\Core\Asset\Tests;

use Neo\Core\Asset\AssetManager;
use Neo\Core\Asset\Exception\AssetException;
use Neo\Core\DI\Container;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\NotFoundExceptionInterface;

class AssetManagerTest extends TestCase
{
    private string $configsDir;
    private string $assetsDir;
    private string $buildsDir;
    private string $publicDir;

    public function setUp(): void
    {
        $base = sys_get_temp_dir() . '/neo-asset-test-' . uniqid();

        $this->configsDir = $base . '/configs';
        $this->assetsDir = $base . '/assets/';
        $this->publicDir = $base . '/public';
        $this->buildsDir = $this->publicDir . '/builds/';

        mkdir($this->configsDir, 0777, true);
        mkdir($this->assetsDir, 0777, true);
        mkdir($this->buildsDir, 0777, true);
    }

    protected function tearDown(): void
    {
        $this->deleteDir(dirname($this->configsDir));
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

    private function makeContainer(string $environment): Container
    {
        $container = new Container();

        $container->instance(Container::class, $container);

        file_put_contents(
            $this->configsDir . '/app.config.php',
            '<?php return ' . var_export(['environment' => $environment], true) . ';'
        );

        $container->set('configsPath', $this->configsDir);
        $container->set('application', 'testapp');
        $container->set('assetsPath', $this->assetsDir);
        $container->set('publicPath', $this->publicDir);
        $container->set('buildsPath', $this->buildsDir);
        $container->set('manifestFilename', 'manifest.json');

        return $container;
    }

    /**
     * @throws ContainerExceptionInterface
     * @throws NotFoundExceptionInterface
     * @throws AssetException
     */
    public function testProdReturnsManifestPathWhenPresent(): void
    {
        $container = $this->makeContainer('prod');

        $manifestPath = $this->buildsDir . 'testapp/manifest.json';
        mkdir(dirname($manifestPath), 0777, true);
        file_put_contents($manifestPath, json_encode([
            'css/app.css' => '/builds/testapp/assets/css/app-deadbeef.min.css',
        ]));

        $manager = new AssetManager($container);

        self::assertSame(
            '/builds/testapp/assets/css/app-deadbeef.min.css',
            $manager->getAssetPath('css/app.css')
        );
    }

    /**
     * @throws ContainerExceptionInterface
     * @throws NotFoundExceptionInterface
     * @throws AssetException
     */
    public function testProdFallsBackToDefaultPathWhenNotInManifest(): void
    {
        $container = $this->makeContainer('prod');

        $manager = new AssetManager($container);

        self::assertSame(
            '/builds/testapp/assets/css/unknown.css',
            $manager->getAssetPath('css/unknown.css')
        );
    }

    /**
     * @throws ContainerExceptionInterface
     * @throws NotFoundExceptionInterface
     * @throws AssetException
     */
    public function testDevThrowsWhenSourceFileIsMissing(): void
    {
        $container = $this->makeContainer('dev');
        $manager = new AssetManager($container);

        $this->expectException(AssetException::class);

        $manager->getAssetPath('img/missing.svg');
    }

    /**
     * @throws ContainerExceptionInterface
     * @throws NotFoundExceptionInterface
     * @throws AssetException
     */
    public function testDevCompilesAssetOnFirstCall(): void
    {
        $container = $this->makeContainer('dev');

        mkdir($this->assetsDir . 'img', 0777, true);
        file_put_contents($this->assetsDir . 'img/logo.svg', '<svg>v1</svg>');

        $manager = new AssetManager($container);
        $result = $manager->getAssetPath('img/logo.svg');

        self::assertStringStartsWith('/builds/testapp/assets/img/', $result);
        self::assertStringEndsWith('.svg', $result);

        $relative = str_replace('/builds/testapp/assets/', '', $result);
        self::assertFileExists($this->buildsDir . 'testapp/assets/' . $relative);

        $manifestPath = $this->buildsDir . 'testapp/manifest.json';
        self::assertFileExists($manifestPath);
        $manifest = json_decode((string) file_get_contents($manifestPath), true);
        self::assertSame($result, $manifest['img/logo.svg']);
    }

    /**
     * @throws NotFoundExceptionInterface
     * @throws AssetException
     * @throws ContainerExceptionInterface
     */
    public function testDevReusesCachedBuildWhenSourceUnchanged(): void
    {
        $container = $this->makeContainer('dev');

        mkdir($this->assetsDir . 'img', 0777, true);
        file_put_contents($this->assetsDir . 'img/logo.svg', '<svg>v1</svg>');

        $manager = new AssetManager($container);

        $first = $manager->getAssetPath('img/logo.svg');
        $second = $manager->getAssetPath('img/logo.svg');

        self::assertSame($first, $second);

        $files = glob($this->buildsDir . 'testapp/assets/img/*');
        self::assertCount(1, $files);
    }

    /**
     * @throws NotFoundExceptionInterface
     * @throws AssetException
     * @throws ContainerExceptionInterface
     */
    public function testDevRecompilesAndRemovesOldBuildWhenSourceChanges(): void
    {
        $container = $this->makeContainer('dev');

        mkdir($this->assetsDir . 'img', 0777, true);
        file_put_contents($this->assetsDir . 'img/logo.svg', '<svg>v1</svg>');

        $manager = new AssetManager($container);
        $first = $manager->getAssetPath('img/logo.svg');

        file_put_contents($this->assetsDir . 'img/logo.svg', '<svg>v2 - contenu different</svg>');

        $second = $manager->getAssetPath('img/logo.svg');

        self::assertNotSame($first, $second);

        $oldReal = $this->publicDir . '/' . ltrim($first, '/');
        self::assertFileDoesNotExist($oldReal);

        $files = glob($this->buildsDir . 'testapp/assets/img/*');
        self::assertCount(1, $files);
    }
}