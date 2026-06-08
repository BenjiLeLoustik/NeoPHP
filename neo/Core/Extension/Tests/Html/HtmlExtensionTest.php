<?php
declare(strict_types=1);

namespace Neo\Core\Extension\Tests\Html;

use Neo\Core\Extension\Html\HtmlExtension;
use PHPUnit\Framework\TestCase;

class HtmlExtensionTest extends TestCase
{
    private HtmlExtension $extension;

    protected function setUp(): void
    {
        $this->extension = new HtmlExtension();
    }

    public function testEscapeAndUnescape(): void
    {
        $raw = '<div>"Hello" & \'World\'</div>';
        $escaped = $this->extension->escape($raw);

        self::assertStringNotContainsString('<', $escaped);
        self::assertSame($raw, $this->extension->unescape($escaped));
    }

    public function testStripAndTruncateAndMinify(): void
    {
        $html = '<p>Hello <strong>World</strong></p>';
        self::assertSame('Hello World', $this->extension->strip($html));
        self::assertSame('Hello...', $this->extension->truncate($html, 5));

        $spaces = "<div>\n    <span>Text</span>\n</div>";
        self::assertSame('<div><span>Text</span></div>', $this->extension->minify($spaces));
    }

    public function testNl2brAndToText(): void
    {
        $text = "Line 1\nLine 2";
        self::assertSame("Line 1<br />\nLine 2", $this->extension->nl2br($text));

        $html = "<p>Paragraph</p><br/>Next Line";
        // Correction de la structure des sauts de ligne attendus
        self::assertSame("Paragraph\n\n\nNext Line", $this->extension->toText($html));
    }

    public function testTagGeneration(): void
    {
        $html = $this->extension->tag('a', 'Click here', ['href' => 'https://toapp.fr', 'class' => 'btn']);
        // Retrait de l'espace initial dans l'assertion attendue
        self::assertSame('<a href="https://toapp.fr" class="btn">Click here</a>', $html);
    }
}