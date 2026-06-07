<?php
declare(strict_types=1);

namespace Neo\Core\Asset\Tests\Handler;

use Neo\Core\Asset\AssetHandler;
use Neo\Core\Asset\Exception\AssetException;
use Neo\Core\DI\Container;
use Neo\Core\Utils\Config\Config;
use PHPUnit\Framework\TestCase;

class AssetHandlerTest extends TestCase
{
    private string $tmpDir;
    private string $buildsDir;
    private string $sourcesDir;
    private string $publicDir;

    private Container $container;
    private Config $config;

    protected function setUp(): void
    {
        $this->tmpDir     = sys_get_temp_dir() . '/neo_asset_handler_' . uniqid();
        $this->sourcesDir = $this->tmpDir . '/assets/';
        $this->buildsDir  = $this->tmpDir . '/builds/';
        $this->publicDir  = $this->tmpDir . '/public';

        mkdir($this->sourcesDir, 0777, true);
        mkdir($this->buildsDir, 0777, true);
        mkdir($this->publicDir, 0777, true);

        $this->config = $this->createStub(Config::class);
        $this->config->method('from')->willReturnSelf();
        $this->config->method('get')->willReturn('dev');

        $this->container = $this->makeContainerStub($this->config, $this->sourcesDir, $this->buildsDir, $this->publicDir);
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

    private function makeContainerStub(Config $config, string $sourcesDir, string $buildsDir, string $publicDir): Container
    {
        $container = $this->createStub(Container::class);
        $container->method('get')->willReturnCallback(fn($key) => match($key) {
            Config::class      => $config,
            'application'      => 'testapp',
            'assetsPath'       => $sourcesDir,
            'publicPath'       => $publicDir,
            'buildsPath'       => $buildsDir,
            'manifestFilename' => 'manifest.json',
        });

        return $container;
    }

    private function makeProdContainer(): Container
    {
        $prodConfig = $this->createStub(Config::class);
        $prodConfig->method('from')->willReturnSelf();
        $prodConfig->method('get')->willReturn('prod');

        return $this->makeContainerStub($prodConfig, $this->sourcesDir, $this->buildsDir, $this->publicDir);
    }

    private function createSourceFile(string $relativePath, string $content): void
    {
        $fullPath = $this->sourcesDir . ltrim($relativePath, '/');
        $dir = dirname($fullPath);

        if (!is_dir($dir)) {
            mkdir($dir, 0777, true);
        }

        file_put_contents($fullPath, $content);
    }

    private function makeHandler(): AssetHandler
    {
        return new AssetHandler($this->container);
    }

    // -------------------------------------------------------------------------
    // Manifest
    // -------------------------------------------------------------------------

    public function testHandlerInstantiatesWithoutManifest(): void
    {
        $handler = $this->makeHandler();
        $this->assertInstanceOf(AssetHandler::class, $handler);
    }

    public function testHandlerLoadsExistingManifest(): void
    {
        $manifestDir = $this->buildsDir . 'testapp/';
        mkdir($manifestDir, 0777, true);

        $manifest = ['/css/app.css' => '/builds/testapp/assets/css/app-abc123.min.css'];
        file_put_contents($manifestDir . 'manifest.json', json_encode($manifest));

        $handler = $this->makeHandler();
        $this->assertInstanceOf(AssetHandler::class, $handler);
    }

    public function testHandlerThrowsOnUnreadableManifest(): void
    {
        $manifestDir = $this->buildsDir . 'testapp/';
        mkdir($manifestDir, 0777, true);

        $manifestPath = $manifestDir . 'manifest.json';
        file_put_contents($manifestPath, '');
        chmod($manifestPath, 0000);

        try {
            $this->makeHandler();
            $this->markTestSkipped('Cannot test unreadable file as current user.');
        } catch (AssetException $e) {
            $this->assertSame(500, $e->getCode());
        } finally {
            chmod($manifestPath, 0644);
        }
    }

    // -------------------------------------------------------------------------
    // getAssetPath — env dev
    // -------------------------------------------------------------------------

    public function testGetAssetPathCompilesCssFile(): void
    {
        $this->createSourceFile('/css/app.css', 'body { margin: 0; padding: 0; }');

        $handler = $this->makeHandler();
        $result  = $handler->getAssetPath('/css/app.css');

        $this->assertStringStartsWith('/builds/testapp/assets/', $result);
        $this->assertStringContainsString('.min.css', $result);
    }

    public function testGetAssetPathCompilesJsFile(): void
    {
        $this->createSourceFile('/js/app.js', 'function hello() { return "world"; }');

        $handler = $this->makeHandler();
        $result  = $handler->getAssetPath('/js/app.js');

        $this->assertStringStartsWith('/builds/testapp/assets/', $result);
        $this->assertStringContainsString('.min.js', $result);
    }

    public function testGetAssetPathCompilesLessFile(): void
    {
        $this->createSourceFile('/css/app.less', '@color: #fff; body { color: @color; }');

        $handler = $this->makeHandler();
        $result  = $handler->getAssetPath('/css/app.less');

        $this->assertStringStartsWith('/builds/testapp/assets/', $result);
        $this->assertStringContainsString('.css', $result);
    }

    public function testGetAssetPathCopiesUnknownExtension(): void
    {
        $this->createSourceFile('/fonts/font.woff2', 'fake-binary-content');

        $handler = $this->makeHandler();
        $result  = $handler->getAssetPath('/fonts/font.woff2');

        $this->assertStringStartsWith('/builds/testapp/assets/', $result);
        $this->assertStringContainsString('.woff2', $result);
    }

    public function testGetAssetPathThrowsWhenSourceMissing(): void
    {
        $this->expectException(AssetException::class);

        $handler = $this->makeHandler();
        $handler->getAssetPath('/css/missing.css');
    }

    public function testGetAssetPathReturnsCachedPathWhenHashUnchanged(): void
    {
        $this->createSourceFile('/css/app.css', 'body { margin: 0; }');

        $handler = $this->makeHandler();
        $first   = $handler->getAssetPath('/css/app.css');
        $second  = $handler->getAssetPath('/css/app.css');

        $this->assertSame($first, $second);
    }

    public function testGetAssetPathRecompilesWhenFileChanges(): void
    {
        $this->createSourceFile('/css/app.css', 'body { margin: 0; }');

        $handler = $this->makeHandler();
        $first   = $handler->getAssetPath('/css/app.css');

        $this->createSourceFile('/css/app.css', 'body { margin: 0; color: red; }');

        $handler2 = $this->makeHandler();
        $second   = $handler2->getAssetPath('/css/app.css');

        $this->assertNotSame($first, $second);
    }

    public function testGetAssetPathWritesManifestAfterCompile(): void
    {
        $this->createSourceFile('/css/app.css', 'body { color: blue; }');

        $handler = $this->makeHandler();
        $handler->getAssetPath('/css/app.css');

        $manifestPath = $this->buildsDir . 'testapp/manifest.json';
        $this->assertFileExists($manifestPath);

        $manifest = json_decode(file_get_contents($manifestPath), true);
        $this->assertArrayHasKey('/css/app.css', $manifest);
    }

    public function testGetAssetPathDeletesOldBuildFileOnRecompile(): void
    {
        $this->createSourceFile('/css/app.css', 'body { margin: 0; }');

        $handler  = $this->makeHandler();
        $firstUrl = $handler->getAssetPath('/css/app.css');

        $oldFile = $this->publicDir . '/' . ltrim($firstUrl, '/');
        $oldDir  = dirname($oldFile);
        if (!is_dir($oldDir)) {
            mkdir($oldDir, 0777, true);
        }
        file_put_contents($oldFile, 'old content');

        $this->createSourceFile('/css/app.css', 'body { color: green; }');

        $handler2  = $this->makeHandler();
        $secondUrl = $handler2->getAssetPath('/css/app.css');

        $this->assertNotSame($firstUrl, $secondUrl);
        $this->assertFileDoesNotExist($oldFile);
    }

    // -------------------------------------------------------------------------
    // getAssetPath — env prod
    // -------------------------------------------------------------------------

    public function testGetAssetPathInProdReturnsManifestEntry(): void
    {
        $manifestDir = $this->buildsDir . 'testapp/';
        mkdir($manifestDir, 0777, true);
        $manifest = ['/css/app.css' => '/builds/testapp/assets/css/app-abc123.min.css'];
        file_put_contents($manifestDir . 'manifest.json', json_encode($manifest));

        $handler = new AssetHandler($this->makeProdContainer());
        $result  = $handler->getAssetPath('/css/app.css');

        $this->assertSame('/builds/testapp/assets/css/app-abc123.min.css', $result);
    }

    public function testGetAssetPathInProdFallsBackWhenNotInManifest(): void
    {
        $handler = new AssetHandler($this->makeProdContainer());
        $result  = $handler->getAssetPath('/css/unknown.css');

        $this->assertSame('/builds/testapp/assets/css/unknown.css', $result);
    }
}