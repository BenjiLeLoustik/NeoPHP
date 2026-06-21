<?php
declare(strict_types=1);

namespace Neo\Core\Extension\Tests;

use Neo\Core\Extension\Html\HtmlExtension;
use PHPUnit\Framework\TestCase;

final class HtmlExtensionTest extends TestCase
{
    private HtmlExtension $html;

    protected function setUp(): void
    {
        $this->html = new HtmlExtension();
    }

    public function testEscape(): void
    {
        self::assertSame('&lt;b&gt;bold&lt;/b&gt;', $this->html->escape('<b>bold</b>'));
    }

    public function testUnescape(): void
    {
        self::assertSame('<b>bold</b>', $this->html->unescape('&lt;b&gt;bold&lt;/b&gt;'));
    }

    public function testStrip(): void
    {
        self::assertSame('Hello', $this->html->strip('<p>Hello</p>'));
    }

    public function testTruncate(): void
    {
        self::assertSame('Hel...', $this->html->truncate('<p>Hello World</p>', 3));
    }

    public function testToText(): void
    {
        self::assertSame('Hello', $this->html->toText('<p>Hello</p>'));
    }

    public function testTag(): void
    {
        self::assertSame('<p class="foo">Hello</p>', $this->html->tag('p', 'Hello', ['class' => 'foo']));
    }

    public function testSelfClosingTag(): void
    {
        self::assertSame('<br />', $this->html->selfClosingTag('br'));
    }

    public function testMinify(): void
    {
        $result = $this->html->minify('<p>  Hello  </p>  <span>World</span>');
        self::assertSame('<p> Hello </p><span>World</span>', $result);
    }
}