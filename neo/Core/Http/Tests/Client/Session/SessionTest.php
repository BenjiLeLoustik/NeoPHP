<?php
declare(strict_types=1);

namespace Neo\Core\Http\Tests\Client\Session;

use Neo\Core\DI\Container;
use Neo\Core\Http\Client\Session\Session;
use Neo\Core\Utils\Config\Config;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class SessionTest extends TestCase
{
    /** @var Container&MockObject */
    private Container $container;

    /** @var Config&MockObject */
    private Config $config;

    private string $storagePath;

    protected function setUp(): void
    {
        $this->container = $this->createMock(Container::class);
        $this->config = $this->createMock(Config::class);

        $this->storagePath = sys_get_temp_dir() . '/neo_session_test_' . uniqid('', true);
        mkdir($this->storagePath, 0775, true);

        $this->container->method('get')->willReturnMap([
            [Config::class, $this->config],
            ['storagePath', $this->storagePath],
        ]);

        $this->config->method('from')->with('session')->willReturn($this->config);
        $_SESSION = [];
    }

    protected function tearDown(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_write_close();
        }

        if (is_dir($this->storagePath)) {
            $this->removeDirectory($this->storagePath);
        }
        $_SESSION = [];
    }

    private function removeDirectory(string $path): void
    {
        if (!is_dir($path)) {
            return;
        }

        $items = scandir($path);
        if ($items === false) {
            return;
        }

        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }

            $full = $path . '/' . $item;

            if (is_dir($full)) {
                $this->removeDirectory($full);
            } else {
                unlink($full);
            }
        }

        rmdir($path);
    }

    public function testSessionInitializationAndCrud(): void
    {
        $this->config->method('get')->with('session')->willReturn([
            'enabled' => true,
            'name' => 'NEOSESSID',
            'lifetime' => 3600,
            'secure' => false,
            'http_only' => true,
            'same_site' => 'Lax',
            'storage' => ['enabled' => false]
        ]);

        $session = new Session($this->container);

        self::assertFalse($session->has('foo'));
        $session->set('foo', 'bar');
        self::assertTrue($session->has('foo'));
        self::assertSame('bar', $session->get('foo'));
        self::assertSame(['foo' => 'bar'], $session->all());

        $session->remove('foo');
        self::assertFalse($session->has('foo'));
        self::assertNull($session->get('foo'));
    }

    public function testSessionClearAndGetDefaultValue(): void
    {
        $this->config->method('get')->with('session')->willReturn([
            'enabled' => false,
            'storage' => ['enabled' => false]
        ]);

        $session = new Session($this->container);
        $session->set('a', 1);
        $session->set('b', 2);

        self::assertSame(1, $session->get('a'));
        self::assertSame('default', $session->get('c', 'default'));

        $session->clear();
        self::assertEmpty($session->all());
    }

    public function testSessionStorageDirectoryCreation(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_write_close();
        }

        $this->config->method('get')->with('session')->willReturn([
            'enabled' => true,
            'name' => 'NEOSESSID',
            'lifetime' => 3600,
            'secure' => false,
            'http_only' => true,
            'same_site' => 'Lax',
            'storage' => [
                'enabled' => true,
                'handler' => 'files'
            ]
        ]);

        new Session($this->container);

        $expectedPath = $this->storagePath . '/var/cache/session/';
        self::assertDirectoryExists($expectedPath);
    }
}