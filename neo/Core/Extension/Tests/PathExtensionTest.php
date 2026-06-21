<?php
declare(strict_types=1);

namespace Neo\Core\Extension\Tests;

use Neo\Core\Extension\Path\PathExtension;
use PHPUnit\Framework\TestCase;

final class PathExtensionTest extends TestCase
{
    private PathExtension $path;

    protected function setUp(): void
    {
        $this->path = new PathExtension();
    }

    public function testJoin(): void
    {
        $sep = DIRECTORY_SEPARATOR;
        self::assertSame("foo{$sep}bar{$sep}baz", $this->path->join('foo', 'bar', 'baz'));
    }

    public function testNormalizeResolvesDoubleDots(): void
    {
        $sep = DIRECTORY_SEPARATOR;
        self::assertSame("foo{$sep}baz", $this->path->normalize("foo/bar/../baz"));
    }

    public function testExtension(): void
    {
        self::assertSame('php', $this->path->extension('file.php'));
    }

    public function testFilename(): void
    {
        self::assertSame('file', $this->path->filename('file.php'));
    }

    public function testBasename(): void
    {
        self::assertSame('file.php', $this->path->basename('/foo/file.php'));
    }

    public function testDirname(): void
    {
        self::assertSame('/foo', $this->path->dirname('/foo/file.php'));
    }

    public function testWithoutExtension(): void
    {
        self::assertSame('file', $this->path->withoutExtension('file.php'));
    }

    public function testChangeExtension(): void
    {
        self::assertSame('file.txt', $this->path->changeExtension('file.php', 'txt'));
    }

    public function testIsAbsolute(): void
    {
        self::assertTrue($this->path->isAbsolute('/var/www'));
        self::assertFalse($this->path->isAbsolute('relative/path'));
    }
}