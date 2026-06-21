<?php
declare(strict_types=1);

namespace Neo\Core\Extension\Tests;

use Neo\Core\Extension\String\StringExtension;
use PHPUnit\Framework\TestCase;

final class StringExtensionTest extends TestCase
{
    private StringExtension $str;

    protected function setUp(): void
    {
        $this->str = new StringExtension();
    }

    public function testSlugify(): void
    {
        self::assertSame('hello-world', $this->str->slugify('Hello World'));
    }

    public function testCamelCase(): void
    {
        self::assertSame('helloWorld', $this->str->camelCase('hello world'));
    }

    public function testPascalCase(): void
    {
        self::assertSame('HelloWorld', $this->str->pascalCase('hello world'));
    }

    public function testSnakeCase(): void
    {
        self::assertSame('hello_world', $this->str->snakeCase('Hello World'));
    }

    public function testKebabCase(): void
    {
        self::assertSame('hello-world', $this->str->kebabCase('Hello World'));
    }

    public function testTruncate(): void
    {
        self::assertSame('Hello...', $this->str->truncate('Hello World', 8));
    }

    public function testTruncateDoesNothingWhenShortEnough(): void
    {
        self::assertSame('Hi', $this->str->truncate('Hi', 10));
    }

    public function testContains(): void
    {
        self::assertTrue($this->str->contains('Hello World', 'World'));
        self::assertFalse($this->str->contains('Hello', 'xyz'));
    }

    public function testStartsWith(): void
    {
        self::assertTrue($this->str->startsWith('Hello', 'He'));
        self::assertFalse($this->str->startsWith('Hello', 'lo'));
    }

    public function testEndsWith(): void
    {
        self::assertTrue($this->str->endsWith('Hello', 'lo'));
        self::assertFalse($this->str->endsWith('Hello', 'He'));
    }

    public function testReplace(): void
    {
        self::assertSame('Hi World', $this->str->replace('Hello World', 'Hello', 'Hi'));
    }

    public function testReplaceFirst(): void
    {
        self::assertSame('X foo foo', $this->str->replaceFirst('foo foo foo', 'foo', 'X'));
    }

    public function testReplaceLast(): void
    {
        self::assertSame('foo foo X', $this->str->replaceLast('foo foo foo', 'foo', 'X'));
    }

    public function testBetween(): void
    {
        self::assertSame('world', $this->str->between('hello [world] !', '[', ']'));
    }

    public function testMask(): void
    {
        self::assertSame('he******ld', $this->str->mask('helloworld', 2, 2));
    }

    public function testPadLeft(): void
    {
        self::assertSame('  hi', $this->str->padLeft('hi', 4));
    }

    public function testPadRight(): void
    {
        self::assertSame('hi  ', $this->str->padRight('hi', 4));
    }

    public function testRepeat(): void
    {
        self::assertSame('ha-ha-ha', $this->str->repeat('ha', 3, '-'));
    }

    public function testSanitize(): void
    {
        self::assertSame('&lt;b&gt;bold&lt;/b&gt;', $this->str->sanitize('<b>bold</b>'));
    }
}