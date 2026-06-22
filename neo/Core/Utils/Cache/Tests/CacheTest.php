<?php
declare(strict_types=1);

namespace Neo\Core\Utils\Cache\Tests;

use Neo\Core\DI\Container;
use Neo\Core\DI\Exception\ContainerException;
use Neo\Core\Utils\Cache\Cache;
use Neo\Core\Utils\Cache\Driver\ArrayDriver;
use Neo\Core\Utils\Cache\Driver\FileDriver;
use Neo\Core\Utils\Cache\Exception\CacheException;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\NotFoundExceptionInterface;

class CacheTest extends TestCase
{
    private string $configsDir;
    private string $storageDir;

    protected function setUp(): void
    {
        $base = sys_get_temp_dir() . '/neo-cache-test-' . uniqid();

        $this->configsDir = $base . '/configs';
        $this->storageDir = $base . '/storage';

        mkdir($this->configsDir, 0777, true);
        mkdir($this->storageDir, 0777, true);
    }

    protected function tearDown(): void
    {
        $this->deleteDir(dirname($this->configsDir));
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
     * @param array<string, mixed> $cacheConfig
     */
    private function makeContainer(array $cacheConfig): Container
    {
        $container = new Container();
        $container->instance(Container::class, $container);

        file_put_contents(
            $this->configsDir . '/cache.config.php',
            '<?php return ' . var_export($cacheConfig, true) . ';'
        );

        $container->set('configsPath', $this->configsDir);
        $container->set('storagePath', $this->storageDir);

        return $container;
    }

    /**
     * @throws CacheException
     * @throws ContainerException
     */
    public function testUsesArrayDriverWhenConfigured(): void
    {
        $cache = new Cache($this->makeContainer(['driver' => 'array']));

        self::assertInstanceOf(ArrayDriver::class, $cache->getDriver());
    }

    /**
     * @throws CacheException
     * @throws ContainerException
     */
    public function testUsesFileDriverByDefault(): void
    {
        $cache = new Cache($this->makeContainer(['ttl' => 3600]));

        self::assertInstanceOf(FileDriver::class, $cache->getDriver());
    }

    /**
     * @throws CacheException
     * @throws ContainerException
     */
    public function testFileDriverUsesConfiguredSubPath(): void
    {
        new Cache($this->makeContainer([
            'driver' => 'files',
            'drivers' => ['files' => ['path' => 'custom-cache']],
        ]));

        self::assertDirectoryExists($this->storageDir . '/custom-cache');
    }

    /**
     * @throws ContainerException
     */
    public function testThrowsForUnsupportedDriver(): void
    {
        $this->expectException(CacheException::class);

        new Cache($this->makeContainer(['driver' => 'unknown']));
    }

    /**
     * @throws CacheException
     * @throws ContainerException
     */
    public function testGetSetDeleteHasAreDelegatedToDriver(): void
    {
        $cache = new Cache($this->makeContainer(['driver' => 'array']));

        $cache->set('name', 'Neo');

        self::assertTrue($cache->has('name'));
        self::assertSame('Neo', $cache->get('name'));

        $cache->delete('name');

        self::assertFalse($cache->has('name'));
    }

    /**
     * @throws CacheException
     * @throws ContainerException
     */
    public function testClearEmptiesTheCache(): void
    {
        $cache = new Cache($this->makeContainer(['driver' => 'array']));

        $cache->set('a', 1);
        $cache->set('b', 2);
        $cache->clear();

        self::assertFalse($cache->has('a'));
        self::assertFalse($cache->has('b'));
    }

    /**
     * @throws CacheException
     * @throws ContainerExceptionInterface
     * @throws NotFoundExceptionInterface
     * @throws ContainerException
     */
    public function testRememberReturnsCallbackValueAndCachesIt(): void
    {
        $cache = new Cache($this->makeContainer(['driver' => 'array']));

        $calls = 0;
        $callback = function () use (&$calls) {
            $calls++;
            return 'computed';
        };

        $first = $cache->remember('key', 60, $callback);
        $second = $cache->remember('key', 60, $callback);

        self::assertSame('computed', $first);
        self::assertSame('computed', $second);
        self::assertSame(1, $calls);
    }
}