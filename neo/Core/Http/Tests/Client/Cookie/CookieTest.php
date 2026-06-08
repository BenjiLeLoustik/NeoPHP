<?php
declare(strict_types=1);

namespace Neo\Core\Http\Tests\Client\Cookie;

use Neo\Core\DI\Container;
use Neo\Core\Http\Client\Cookie\Cookie;
use Neo\Core\Utils\Config\Config;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class CookieTest extends TestCase
{
    /** @var Container&MockObject */
    private Container $container;

    /** @var Config&MockObject */
    private Config $config;

    protected function setUp(): void
    {
        $this->container = $this->createMock(Container::class);
        $this->config = $this->createMock(Config::class);

        $this->container->method('get')->with(Config::class)->willReturn($this->config);
        $this->config->method('from')->with('session')->willReturn($this->config);

        $_COOKIE = [];
    }

    protected function tearDown(): void
    {
        $_COOKIE = [];
    }

    public function testCookieSetGetHasAndRemove(): void
    {
        $this->config->method('get')->with('cookie')->willReturn([
            'prefix' => 'neo_',
            'lifetime' => 3600,
            'path' => '/',
            'domain' => '',
            'secure' => false,
            'http_only' => true,
            'same_site' => 'Lax'
        ]);

        $cookie = new Cookie($this->container);

        self::assertFalse($cookie->has('test'));

        @$cookie->set('test', 'value');

        self::assertTrue($cookie->has('test'));
        self::assertSame('value', $cookie->get('test'));
        self::assertSame('value', $_COOKIE['neo_test']);

        @$cookie->remove('test');
        self::assertFalse($cookie->has('test'));
        self::assertNull($cookie->get('test'));
    }

    public function testCookieAllReturnsGlobalCookieArray(): void
    {
        $this->config->method('get')->with('cookie')->willReturn(['prefix' => '']);
        $cookie = new Cookie($this->container);

        $_COOKIE['direct_key'] = 'direct_value';
        self::assertSame($_COOKIE, $cookie->all());
    }
}