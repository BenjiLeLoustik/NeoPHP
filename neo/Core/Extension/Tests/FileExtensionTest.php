<?php
declare(strict_types=1);

namespace Neo\Core\Extension\Tests;

use Neo\Core\Extension\File\FileExtension;
use PHPUnit\Framework\TestCase;

final class FileExtensionTest extends TestCase
{
    private FileExtension $file;
    private string $tmpDir;

    protected function setUp(): void
    {
        $this->file   = new FileExtension();
        $this->tmpDir = sys_get_temp_dir() . '/neo-file-test-' . uniqid();
        mkdir($this->tmpDir, 0777, true);
    }

    protected function tearDown(): void
    {
        $this->file->deleteDirectory($this->tmpDir);
    }

    private function tmp(string $name): string
    {
        return $this->tmpDir . '/' . $name;
    }

    public function testExtension(): void
    {
        self::assertSame('php', $this->file->extension('file.php'));
    }

    public function testFilenameWithExtension(): void
    {
        self::assertSame('file.php', $this->file->filename('/foo/file.php'));
    }

    public function testFilenameWithoutExtension(): void
    {
        self::assertSame('file', $this->file->filename('/foo/file.php', withExtension: false));
    }

    public function testDirectory(): void
    {
        self::assertSame('/foo', $this->file->directory('/foo/file.php'));
    }

    public function testWriteAndRead(): void
    {
        $path = $this->tmp('test.txt');
        $this->file->write($path, 'hello');
        self::assertSame('hello', $this->file->read($path));
    }

    public function testAppend(): void
    {
        $path = $this->tmp('test.txt');
        $this->file->write($path, 'hello');
        $this->file->write($path, ' world', append: true);
        self::assertSame('hello world', $this->file->read($path));
    }

    public function testExists(): void
    {
        $path = $this->tmp('exists.txt');
        self::assertFalse($this->file->exists($path));
        $this->file->write($path, '');
        self::assertTrue($this->file->exists($path));
    }

    public function testDelete(): void
    {
        $path = $this->tmp('delete.txt');
        $this->file->write($path, '');
        $this->file->delete($path);
        self::assertFalse($this->file->exists($path));
    }

    public function testSize(): void
    {
        $path = $this->tmp('size.txt');
        $this->file->write($path, 'hello');
        self::assertSame(5, $this->file->size($path));
    }

    public function testReadLines(): void
    {
        $path = $this->tmp('lines.txt');
        $this->file->write($path, "line1\nline2\nline3");
        self::assertSame(['line1', 'line2', 'line3'], $this->file->readLines($path));
    }

    public function testReadJson(): void
    {
        $path = $this->tmp('data.json');
        $this->file->write($path, '{"foo":"bar"}');
        self::assertSame(['foo' => 'bar'], $this->file->readJson($path));
    }

    public function testWriteJson(): void
    {
        $path = $this->tmp('data.json');
        $this->file->writeJson($path, ['foo' => 'bar'], pretty: false);
        self::assertSame(['foo' => 'bar'], $this->file->readJson($path));
    }

    public function testIsImage(): void
    {
        self::assertTrue($this->file->isImage('photo.jpg'));
        self::assertFalse($this->file->isImage('document.pdf'));
    }

    public function testSanitizeFilename(): void
    {
        self::assertSame('hello_world.txt', $this->file->sanitizeFilename('hello world.txt'));
    }
}