<?php
declare(strict_types=1);

namespace Neo\Core\Extension\Tests;

use Neo\Core\Extension\Url\UrlExtension;
use PHPUnit\Framework\TestCase;

final class UrlExtensionTest extends TestCase
{
    private UrlExtension $url;

    protected function setUp(): void
    {
        $this->url = new UrlExtension();
    }

    public function testScheme(): void
    {
        self::assertSame('https', $this->url->scheme('https://example.com'));
    }

    public function testHost(): void
    {
        self::assertSame('example.com', $this->url->host('https://example.com/path'));
    }

    public function testPath(): void
    {
        self::assertSame('/path', $this->url->path('https://example.com/path'));
    }

    public function testQueryParams(): void
    {
        self::assertSame(['foo' => 'bar'], $this->url->queryParams('https://example.com?foo=bar'));
    }

    public function testFragment(): void
    {
        self::assertSame('section', $this->url->fragment('https://example.com#section'));
    }

    public function testIsValid(): void
    {
        self::assertTrue($this->url->isValid('https://example.com'));
        self::assertFalse($this->url->isValid('not a url'));
    }

    public function testIsAbsolute(): void
    {
        self::assertTrue($this->url->isAbsolute('https://example.com'));
        self::assertFalse($this->url->isAbsolute('/relative'));
    }

    public function testIsSameDomain(): void
    {
        self::assertTrue($this->url->isSameDomain('https://example.com/a', 'https://example.com/b'));
        self::assertFalse($this->url->isSameDomain('https://example.com', 'https://other.com'));
    }

    public function testAddQueryParams(): void
    {
        $result = $this->url->addQueryParams('https://example.com', ['foo' => 'bar']);
        self::assertStringContainsString('foo=bar', $result);
    }

    public function testRemoveQueryParam(): void
    {
        $result = $this->url->removeQueryParam('https://example.com?foo=bar&baz=1', 'foo');
        self::assertStringNotContainsString('foo', $result);
        self::assertStringContainsString('baz=1', $result);
    }

    public function testEncode(): void
    {
        self::assertSame('hello+world', $this->url->encode('hello world'));
    }

    public function testDecode(): void
    {
        self::assertSame('hello world', $this->url->decode('hello+world'));
    }
}