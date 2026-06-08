<?php
declare(strict_types=1);

namespace Neo\Core\View\Tests;

use Neo\Core\DI\Container;
use Neo\Core\Utils\Config\Config;
use Neo\Core\View\Exception\ViewException;
use Neo\Core\View\Interface\TwigExtensionInterface;
use Neo\Core\View\View;
use PHPUnit\Framework\TestCase;

class ViewTest extends TestCase
{
    private Container $container;
    private string $tempViewsPath;

    protected function setUp(): void
    {
        $this->container = new Container();
        $this->tempViewsPath = sys_get_temp_dir() . '/neo_views_test_' . uniqid();
        mkdir($this->tempViewsPath);
        $this->container->set('viewsPath', $this->tempViewsPath);
        $this->container->set('storagePath', sys_get_temp_dir());

        $configMock = $this->createMock(Config::class);
        $configMock->method('from')->willReturnSelf();

        $configMock->method('all')->willReturn([
            'cache' => false,
            'debug' => false
        ]);

        $configMock->method('get')->willReturn('UTC');
        $this->container->set(Config::class, $configMock);
    }

    protected function tearDown(): void
    {
        if (file_exists($this->tempViewsPath)) {
            array_map('unlink', glob("$this->tempViewsPath/*"));
            rmdir($this->tempViewsPath);
        }
    }

    public function testRenderSuccess(): void
    {
        file_put_contents($this->tempViewsPath . '/hello.twig', 'Hello {{ name }}!');

        $view = new View($this->container);
        $content = $view->render('hello.twig', ['name' => 'Neo']);

        self::assertSame('Hello Neo!', $content);
    }

    public function testRenderThrowsViewExceptionOnMissingTemplate(): void
    {
        $view = new View($this->container);

        $this->expectException(ViewException::class);
        $view->render('missing_template.twig');
    }

    public function testRenderIfExistsReturnsNullOnMissingTemplate(): void
    {
        $view = new View($this->container);
        $result = $view->renderIfExists('missing_template.twig');

        self::assertNull($result);
    }

    public function testAddExtensionRegistersFunctionsAndFilters(): void
    {
        $view = new View($this->container);

        $extensionMock = $this->createMock(TwigExtensionInterface::class);
        $extensionMock->method('getFunctions')->willReturn([
            'test_func' => ['callable' => fn() => 'func_output', 'options' => []]
        ]);
        $extensionMock->method('getFilters')->willReturn([
            'test_filter' => ['callable' => fn(string $s) => strtoupper($s), 'options' => []]
        ]);

        $view->addExtension($extensionMock);

        $twig = $view->getTwig();
        self::assertNotNull($twig->getFunction('test_func'));
        self::assertNotNull($twig->getFilter('test_filter'));
    }
}