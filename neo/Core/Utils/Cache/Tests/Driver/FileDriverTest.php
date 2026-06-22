<?php
declare(strict_types=1);

namespace Neo\Core\Utils\Cache\Tests\Driver;

use Neo\Core\Utils\Cache\Driver\FileDriver;
use Neo\Core\Utils\Cache\Exception\CacheException;
use PHPUnit\Framework\TestCase;

class FileDriverTest extends TestCase
{
    private string $directory;

    protected function setUp(): void
    {
        $this->directory = sys_get_temp_dir() . '/neo-cache-test-' . uniqid();
    }

    protected function tearDown(): void
    {
        $this->deleteDir($this->directory);
    }

    private function deleteDir(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }

        foreach (scandir($dir) as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }
            $path = $dir . '/' . $item;
            is_dir($path) ? $this->deleteDir($path) : unlink($path);
        }

        rmdir($dir);
    }

    /**
     * @throws CacheException
     */
    public function testConstructorCreatesDirectoryWhenMissing(): void
    {
        new FileDriver($this->directory);

        self::assertDirectoryExists($this->directory);
    }

    /**
     * @throws CacheException
     */
    public function testGetReturnsDefaultWhenKeyIsMissing(): void
    {
        $driver = new FileDriver($this->directory);

        self::assertSame('fallback', $driver->get('missing', 'fallback'));
    }

    /**
     * @throws CacheException
     */
    public function testSetThenGetReturnsStoredValue(): void
    {
        $driver = new FileDriver($this->directory);
        $driver->set('name', ['Neo', 'Core']);

        self::assertSame(['Neo', 'Core'], $driver->get('name'));
    }

    /**
     * @throws CacheException
     */
    public function testHasReturnsTrueForExistingKey(): void
    {
        $driver = new FileDriver($this->directory);
        $driver->set('name', 'Neo');

        self::assertTrue($driver->has('name'));
    }

    /**
     * @throws CacheException
     */
    public function testHasReturnsFalseAndPurgesExpiredEntry(): void
    {
        $driver = new FileDriver($this->directory);
        $driver->set('name', 'Neo', -10);

        self::assertFalse($driver->has('name'));
        self::assertNull($driver->get('name'));
    }

    /**
     * @throws CacheException
     */
    public function testDeleteRemovesCacheFile(): void
    {
        $driver = new FileDriver($this->directory);
        $driver->set('name', 'Neo');
        $driver->delete('name');

        self::assertFalse($driver->has('name'));
        self::assertCount(0, glob($this->directory . '/*.cache'));
    }

    /**
     * @throws CacheException
     */
    public function testClearRemovesAllCacheFiles(): void
    {
        $driver = new FileDriver($this->directory);
        $driver->set('a', 1);
        $driver->set('b', 2);

        $driver->clear();

        self::assertCount(0, glob($this->directory . '/*.cache'));
    }

    /**
     * @throws CacheException
     */
    public function testGetReturnsDefaultWhenFileContentIsCorrupted(): void
    {
        $driver = new FileDriver($this->directory);
        $driver->set('name', 'Neo');

        $files = glob($this->directory . '/*.cache');
        file_put_contents($files[0], serialize('not-an-array'));

        self::assertSame('fallback', $driver->get('name', 'fallback'));
    }
}