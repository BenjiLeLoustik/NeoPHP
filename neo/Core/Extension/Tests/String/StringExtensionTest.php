<?php
declare(strict_types=1);

namespace Neo\Core\Extension\Tests\String;

use Neo\Core\Extension\String\StringExtension;
use PHPUnit\Framework\TestCase;

class StringExtensionTest extends TestCase
{
    private StringExtension $extension;

    protected function setUp(): void
    {
        $this->extension = new StringExtension();
    }

    public function testCaseMutationsAndSlugify(): void
    {
        $text = 'Hello World_test-string';

        self::assertSame('hello-world-test-string', $this->extension->slugify($text));
        self::assertSame('helloWorldTestString', $this->extension->camelCase($text));
        self::assertSame('HelloWorldTestString', $this->extension->pascalCase($text));
        self::assertSame('hello_world_test_string', $this->extension->snakeCase($text));
    }

    public function testSanitizationsAndSpacesAndAccents(): void
    {
        self::assertSame('l-ete-est-la', $this->extension->slugify('L\'été est là !'));
        self::assertSame('Word', $this->extension->stripSpaces("  \n Word  \r "));

        $rawHtml = '<script>alert(1)</script>';
        self::assertSame('alert(1)', $this->extension->stripTags($rawHtml));
    }

    public function testHtmlEscapesAndMasking(): void
    {
        $raw = '<strong>"Neo"</strong>';
        $escaped = $this->extension->escapeHtml($raw);

        self::assertStringNotContainsString('<', $escaped);
        self::assertSame($raw, $this->extension->unescapeHtml($escaped));

        // Masking sensitive text
        self::assertSame('12******89', $this->extension->mask('1234567889', 2, 2));
    }
}