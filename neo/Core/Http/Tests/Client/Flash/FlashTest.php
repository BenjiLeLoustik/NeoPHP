<?php
declare(strict_types=1);

namespace Neo\Core\Http\Tests\Client\Flash;

use Neo\Core\DI\Container;
use Neo\Core\Error\Exception\FrameworkException;
use Neo\Core\Http\Client\Flash\Flash;
use Neo\Core\Http\Client\Session\Session;
use Neo\Core\Utils\Config\Config;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class FlashTest extends TestCase
{
    /** @var Container&MockObject */
    private Container $container;

    /** @var Config&MockObject */
    private Config $config;

    /** @var Session&MockObject */
    private Session $sessionMock;

    protected function setUp(): void
    {
        $this->container = $this->createMock(Container::class);
        $this->config = $this->createMock(Config::class);
        $this->sessionMock = $this->createMock(Session::class);

        $this->config->method('from')->with('session')->willReturn($this->config);
        $this->config->method('get')->with('flash')->willReturn([
            'session_key' => '_flash',
            'types' => ['success', 'error', 'info'],
            'auto_expire' => true
        ]);

        $this->container->method('get')->willReturnMap([
            [Config::class, $this->config],
            [Session::class, $this->sessionMock],
        ]);
    }

    public function testFlashInitializationWhenEmpty(): void
    {
        $this->sessionMock->expects(self::once())
            ->method('has')
            ->with('_flash')
            ->willReturn(false);

        $this->sessionMock->expects(self::once())
            ->method('set')
            ->with('_flash', []);

        new Flash($this->container);
    }

    public function testAddFlashMessageSuccess(): void
    {
        $this->sessionMock->method('has')->with('_flash')->willReturn(true);
        $this->sessionMock->method('get')->with('_flash', [])->willReturn([]);

        $this->sessionMock->expects(self::once())
            ->method('set')
            ->with('_flash', [['type' => 'success', 'message' => 'Bravo!']]);

        $flash = new Flash($this->container);
        $flash->add('success', 'Bravo!');
    }

    public function testAddFlashMessageThrowsOnInvalidType(): void
    {
        $this->sessionMock->method('has')->with('_flash')->willReturn(true);

        $flash = new Flash($this->container);

        $this->expectException(FrameworkException::class);
        $this->expectExceptionMessage("Type de flash invalide : 'invalid_type'");

        $flash->add('invalid_type', 'Oups');
    }

    public function testGetAllWithAutoExpire(): void
    {
        $this->sessionMock->method('has')->with('_flash')->willReturn(true);

        $storedMessages = [['type' => 'error', 'message' => 'Aïe']];
        $this->sessionMock->method('get')->with('_flash', [])->willReturn($storedMessages);

        $this->sessionMock->expects(self::once())
            ->method('set')
            ->with('_flash', []);

        $flash = new Flash($this->container);

        self::assertTrue($flash->has());
        self::assertSame($storedMessages, $flash->getAll());
    }

    public function testRenderEscapesHtmlAndOutputsCorrectFormat(): void
    {
        $this->sessionMock->method('has')->with('_flash')->willReturn(true);

        $storedMessages = [
            ['type' => 'info', 'message' => '<strong>Alerte</strong>']
        ];
        $this->sessionMock->method('get')->with('_flash', [])->willReturn($storedMessages);

        $flash = new Flash($this->container);
        $html = $flash->render();

        self::assertStringContainsString("class='flash-message info'", $html);
        self::assertStringContainsString("&lt;strong&gt;Alerte&lt;/strong&gt;", $html);
    }
}