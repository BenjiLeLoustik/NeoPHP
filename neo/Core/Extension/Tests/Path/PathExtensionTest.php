<?php
declare(strict_types=1);

namespace Neo\Core\Extension\Tests\Path;

use Neo\Core\Extension\Path\PathExtension;
use PHPUnit\Framework\TestCase;

class PathExtensionTest extends TestCase
{
    private PathExtension $extension;

    protected function setUp(): void
    {
        $this->extension = new PathExtension();
    }

    public function testJoinAndNormalizePaths(): void
    {
        $ds = DIRECTORY_SEPARATOR;

        $joined = $this->extension->join('foo', 'bar', '..', 'baz');
        self::assertSame("foo{$ds}baz", $joined);

        $normalized = $this->extension->normalize("foo/bar/../baz");
        self::assertSame("foo{$ds}baz", $normalized);
    }

    public function testIsAbsolute(): void
    {
        self::assertTrue($this->extension->isAbsolute('/var/log'));
        self::assertTrue($this->extension->isAbsolute('C:\\Windows'));
        self::assertFalse($this->extension->isAbsolute('relative/path'));
    }

    public function testPathInfoExtractions(): void
    {
        $path = '/var/www/app.min.js';

        self::assertSame('js', $this->extension->extension($path));
        self::assertSame('app.min', $this->extension->filename($path));
        self::assertSame('app.min.js', $this->extension->basename($path));
        self::assertSame('/var/www', $this->extension->dirname($path));
    }

    public function testRelativePathResolution(): void
    {
        $ds = DIRECTORY_SEPARATOR;
        $from = "a{$ds}b{$ds}c";
        $to = "a{$ds}d{$ds}e";

        self::assertSame("..{$ds}..{$ds}d{$ds}e", $this->extension->relative($from, $to));
    }
}