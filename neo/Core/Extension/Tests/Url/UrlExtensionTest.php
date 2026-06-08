<?php
declare(strict_types=1);

namespace Neo\Core\Extension\Tests\Url;

use Neo\Core\Extension\Url\UrlExtension;
use PHPUnit\Framework\TestCase;

class UrlExtensionTest extends TestCase
{
    private UrlExtension $extension;

    protected function setUp(): void
    {
        $this->extension = new UrlExtension();
    }

    public function testParseUrlParts(): void
    {
        $url = 'https://toapp.fr/events?search=rock#top';

        self::assertSame('https', $this->extension->scheme($url));
        self::assertSame('toapp.fr', $this->extension->host($url));
        self::assertSame('/events', $this->extension->path($url));
        self::assertSame('search=rock', $this->extension->queryString($url));
        self::assertSame(['search' => 'rock'], $this->extension->queryParams($url));
        self::assertSame('top', $this->extension->fragment($url));
    }

    public function testUrlValidationsAndDomainMatching(): void
    {
        $url1 = 'https://toapp.fr/about';
        $url2 = 'http://toapp.fr/contact';

        self::assertTrue($this->extension->isValid($url1));
        self::assertFalse($this->extension->isValid('not-a-valid-url'));

        self::assertTrue($this->extension->isAbsolute($url1));
        self::assertTrue($this->extension->isRelative('/local/path'));

        self::assertTrue($this->extension->isSameDomain($url1, $url2));
    }

    public function testEncodingAndUrlNormalizations(): void
    {
        self::assertSame('%2Fevents%2Frock+concert', $this->extension->encode('/events/rock concert'));
        self::assertSame('/events/rock concert', $this->extension->decode('%2Fevents%2Frock+concert'));

        self::assertSame('/blog/my-first-post-', $this->extension->slugifyPath('/Blog/My First Post!'));
    }
}