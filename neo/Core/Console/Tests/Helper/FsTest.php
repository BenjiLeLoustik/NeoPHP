<?php
declare(strict_types=1);

namespace Neo\Core\Console\Tests\Helper;

use Neo\Core\Console\Helper\Fs;
use PHPUnit\Framework\TestCase;

class FsTest extends TestCase
{
    private string $tmpDir;

    protected function setUp(): void
    {
        $this->tmpDir = sys_get_temp_dir() . '/neo_console_fs_' . uniqid();
        mkdir($this->tmpDir, 0777, true);
    }

    protected function tearDown(): void
    {
        if (is_dir($this->tmpDir)) {
            Fs::deleteDir($this->tmpDir);
        }
    }

    // -------------------------------------------------------------------------
    // deleteDir()
    // -------------------------------------------------------------------------

    public function testDeleteDirRemovesDirectoryRecursively(): void
    {
        $dir = $this->tmpDir . '/nested/deep';
        mkdir($dir, 0777, true);
        file_put_contents($dir . '/file.txt', 'content');

        Fs::deleteDir($this->tmpDir . '/nested');

        $this->assertDirectoryDoesNotExist($this->tmpDir . '/nested');
    }

    public function testDeleteDirDoesNothingWhenDirNotExists(): void
    {
        $this->expectNotToPerformAssertions();
        Fs::deleteDir($this->tmpDir . '/nonexistent');
    }

    // -------------------------------------------------------------------------
    // emptyDir()
    // -------------------------------------------------------------------------

    public function testEmptyDirRemovesContentsButKeepsDir(): void
    {
        $sub = $this->tmpDir . '/sub';
        mkdir($sub);
        file_put_contents($this->tmpDir . '/file.txt', 'hello');
        file_put_contents($sub . '/nested.txt', 'world');

        Fs::emptyDir($this->tmpDir);

        $this->assertDirectoryExists($this->tmpDir);
        $this->assertFileDoesNotExist($this->tmpDir . '/file.txt');
        $this->assertDirectoryDoesNotExist($sub);
    }

    public function testEmptyDirDoesNothingWhenDirNotExists(): void
    {
        $this->expectNotToPerformAssertions();
        Fs::emptyDir($this->tmpDir . '/nonexistent');
    }

    // -------------------------------------------------------------------------
    // ensureDir()
    // -------------------------------------------------------------------------

    public function testEnsureDirCreatesDirectory(): void
    {
        $newDir = $this->tmpDir . '/created/nested';
        $this->assertDirectoryDoesNotExist($newDir);

        Fs::ensureDir($newDir);

        $this->assertDirectoryExists($newDir);
    }

    public function testEnsureDirDoesNotThrowIfAlreadyExists(): void
    {
        $this->expectNotToPerformAssertions();
        Fs::ensureDir($this->tmpDir);
    }

    // -------------------------------------------------------------------------
    // pascalCase()
    // -------------------------------------------------------------------------

    public function testPascalCaseConvertsSimpleString(): void
    {
        $this->assertSame('HelloWorld', Fs::pascalCase('hello world'));
    }

    public function testPascalCaseHandlesSpecialCharacters(): void
    {
        $this->assertSame('MyApp', Fs::pascalCase('my-app'));
    }

    public function testPascalCaseHandlesMultipleSeparators(): void
    {
        $this->assertSame('FooBarBaz', Fs::pascalCase('foo_bar-baz'));
    }

    public function testPascalCaseNormalizesAlreadyPascalCase(): void
    {
        // strtolower() est appliqué avant ucwords() — les majuscules internes sont normalisées
        $this->assertSame('Helloworld', Fs::pascalCase('HelloWorld'));
    }

    public function testPascalCaseHandlesUppercaseInput(): void
    {
        $this->assertSame('Hello', Fs::pascalCase('HELLO'));
    }

    // -------------------------------------------------------------------------
    // normalizeDir()
    // -------------------------------------------------------------------------

    public function testNormalizeDirStripsLeadingSlash(): void
    {
        $this->assertSame('foo/bar', Fs::normalizeDir('/foo/bar'));
    }

    public function testNormalizeDirStripsTrailingSlash(): void
    {
        $this->assertSame('foo/bar', Fs::normalizeDir('foo/bar/'));
    }

    public function testNormalizeDirStripsBoth(): void
    {
        $this->assertSame('foo/bar', Fs::normalizeDir('/foo/bar/'));
    }

    public function testNormalizeDirLeavesCleanPathUntouched(): void
    {
        $this->assertSame('foo/bar', Fs::normalizeDir('foo/bar'));
    }
}