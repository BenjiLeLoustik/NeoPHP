<?php
declare(strict_types=1);

namespace Neo\Core\Asset\Tests\Extension;

use Neo\Core\Asset\AssetHandler;
use Neo\Core\Asset\AssetViewExtension;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class AssetViewExtensionTest extends TestCase
{
    private AssetHandler $handler;
    private AssetViewExtension $extension;

    protected function setUp(): void
    {
        $this->handler = $this->createStub(AssetHandler::class);
        $this->extension = new AssetViewExtension($this->handler);
    }

    public function testGetFunctionsContainsAssetKey(): void
    {
        $this->assertArrayHasKey('asset', $this->extension->getFunctions());
    }

    public function testAssetFunctionHasCallable(): void
    {
        $functions = $this->extension->getFunctions();

        $this->assertArrayHasKey('callable', $functions['asset']);
        $this->assertIsCallable($functions['asset']['callable']);
    }

    public function testAssetFunctionHasOptions(): void
    {
        $functions = $this->extension->getFunctions();

        $this->assertArrayHasKey('options', $functions['asset']);
        $this->assertIsArray($functions['asset']['options']);
    }

    public function testAssetFunctionCallsDelegateToHandler(): void
    {
        /** @var AssetHandler&MockObject $handler */
        $handler = $this->createMock(AssetHandler::class);
        $handler
            ->expects($this->once())
            ->method('getAssetPath')
            ->with('/css/app.css')
            ->willReturn('/builds/app/assets/css/app-abc123.min.css');

        $extension = new AssetViewExtension($handler);
        $functions = $extension->getFunctions();
        $result = ($functions['asset']['callable'])('/css/app.css');

        $this->assertSame('/builds/app/assets/css/app-abc123.min.css', $result);
    }

    public function testGetFiltersReturnsEmpty(): void
    {
        $this->assertEmpty($this->extension->getFilters());
    }
}