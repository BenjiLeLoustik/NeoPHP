<?php
declare(strict_types=1);

namespace Neo\Core\Console\Tests;

use Neo\Core\Console\Helper\Fs;
use PHPUnit\Framework\TestCase;

final class FsTest extends TestCase
{
    private string $tmpDir;

    protected function setUp(): void
    {
        $this->tmpDir = sys_get_temp_dir() . '/neo-fs-test-' . uniqid();
        mkdir($this->tmpDir, 0777, true);
    }

    protected function tearDown(): void
    {
        if (is_dir($this->tmpDir)) {
            Fs::deleteDir($this->tmpDir);
        }
    }

    public function testPascalCaseConvertsKebabCase(): void
    {
        self::assertSame('My_Command_Name', Fs::pascalCase('my-command-name'));
    }

    public function testPascalCaseConvertsSnakeCaseAndTrims(): void
    {
        self::assertSame('Hello_World', Fs::pascalCase('  hello_world  '));
    }

    public function testPascalCaseHandlesMultipleSeparators(): void
    {
        self::assertSame('Foo_Bar_Baz', Fs::pascalCase('foo__bar--baz'));
    }

    public function testNormalizeDirTrimsLeadingAndTrailingSlashes(): void
    {
        self::assertSame('foo/bar', Fs::normalizeDir('/foo/bar/'));
    }

    public function testNormalizeDirLeavesCleanPathUnchanged(): void
    {
        self::assertSame('foo/bar', Fs::normalizeDir('foo/bar'));
    }

    public function testEnsureDirCreatesMissingDirectoryRecursively(): void
    {
        $target = $this->tmpDir . '/a/b/c';

        self::assertDirectoryDoesNotExist($target);

        Fs::ensureDir($target);

        self::assertDirectoryExists($target);
    }

    public function testEnsureDirDoesNothingWhenDirectoryAlreadyExists(): void
    {
        $target = $this->tmpDir . '/existing';
        mkdir($target);
        file_put_contents($target . '/keep.txt', 'content');

        Fs::ensureDir($target);

        self::assertFileExists($target . '/keep.txt');
    }

    public function testDeleteDirRemovesDirectoryAndItsContentsRecursively(): void
    {
        mkdir($this->tmpDir . '/sub/nested', 0777, true);
        file_put_contents($this->tmpDir . '/file.txt', 'a');
        file_put_contents($this->tmpDir . '/sub/nested/file2.txt', 'b');

        Fs::deleteDir($this->tmpDir);

        self::assertDirectoryDoesNotExist($this->tmpDir);
    }

    public function testDeleteDirOnMissingDirectoryDoesNothing(): void
    {
        Fs::deleteDir($this->tmpDir . '/does-not-exist');
        $this->expectNotToPerformAssertions();
    }

    public function testEmptyDirRemovesContentsButKeepsTheDirectory(): void
    {
        mkdir($this->tmpDir . '/sub', 0777, true);
        file_put_contents($this->tmpDir . '/file.txt', 'a');
        file_put_contents($this->tmpDir . '/sub/file2.txt', 'b');

        Fs::emptyDir($this->tmpDir);

        self::assertDirectoryExists($this->tmpDir);
        self::assertCount(0, glob($this->tmpDir . '/*'));
    }
}