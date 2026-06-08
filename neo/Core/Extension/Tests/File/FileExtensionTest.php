<?php
declare(strict_types=1);

namespace Neo\Core\Extension\Tests\File;

use Neo\Core\Extension\File\FileExtension;
use PHPUnit\Framework\TestCase;

class FileExtensionTest extends TestCase
{
    private FileExtension $extension;
    private string $tmpDir;

    protected function setUp(): void
    {
        $this->extension = new FileExtension();
        $this->tmpDir = sys_get_temp_dir() . '/neo_file_ext_test_' . uniqid('', true);
        mkdir($this->tmpDir, 0775, true);
    }

    protected function tearDown(): void
    {
        if (is_dir($this->tmpDir)) {
            $this->extension->deleteDirectory($this->tmpDir);
        }
    }

    public function testPathInfoHelpers(): void
    {
        $path = '/var/www/index.php';
        self::assertSame('php', $this->extension->extension($path));
        self::assertSame('index.php', $this->extension->filename($path, true));
        self::assertSame('index', $this->extension->filename($path, false));
        self::assertSame('/var/www', $this->extension->directory($path));
    }

    public function testFileLifecycleAndSizes(): void
    {
        $file = $this->tmpDir . '/test.txt';
        self::assertFalse($this->extension->exists($file));

        file_put_contents($file, str_repeat('A', 1024)); // 1 KB
        self::assertTrue($this->extension->exists($file));
        self::assertSame(1024, $this->extension->size($file));
        self::assertSame('1 KB', $this->extension->humanSize($file));

        $newPath = $this->tmpDir . '/moved.txt';
        self::assertTrue($this->extension->move($file, $newPath));
        self::assertFalse($this->extension->exists($file));
        self::assertTrue($this->extension->exists($newPath));

        self::assertTrue($this->extension->delete($newPath));
        self::assertFalse($this->extension->exists($newPath));
    }

    public function testEnsureAndListDirectoriesAndFiles(): void
    {
        $subDir = $this->tmpDir . '/nested/cache';
        self::assertTrue($this->extension->ensureDirectory($subDir));
        self::assertDirectoryExists($subDir);

        file_put_contents($subDir . '/a.png', 'img');
        file_put_contents($subDir . '/b.txt', 'txt');

        $allFiles = $this->extension->listFiles($subDir);
        self::assertCount(2, $allFiles);

        $pngOnly = $this->extension->listFiles($subDir, 'png');
        self::assertCount(1, $pngOnly);
        self::assertContains('a.png', $pngOnly);
    }
}