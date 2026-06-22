<?php
declare(strict_types=1);

namespace Neo\Core\Utils\Cache\Tests\Driver;

use Neo\Core\Utils\Cache\Driver\ArrayDriver;
use PHPUnit\Framework\TestCase;

class ArrayDriverTest extends TestCase
{
    public function testGetReturnsDefaultWhenKeyIsMissing(): void
    {
        $driver = new ArrayDriver();

        self::assertSame('fallback', $driver->get('missing', 'fallback'));
    }

    public function testSetThenGetReturnsStoredValue(): void
    {
        $driver = new ArrayDriver();
        $driver->set('name', 'Neo');

        self::assertSame('Neo', $driver->get('name'));
    }

    public function testHasReturnsTrueForExistingKey(): void
    {
        $driver = new ArrayDriver();
        $driver->set('name', 'Neo');

        self::assertTrue($driver->has('name'));
    }

    public function testHasReturnsFalseForMissingKey(): void
    {
        $driver = new ArrayDriver();

        self::assertFalse($driver->has('missing'));
    }

    public function testHasReturnsFalseAndPurgesExpiredEntry(): void
    {
        $driver = new ArrayDriver();
        $driver->set('name', 'Neo', -10);

        self::assertFalse($driver->has('name'));
        self::assertNull($driver->get('name'));
    }

    public function testDeleteRemovesKey(): void
    {
        $driver = new ArrayDriver();
        $driver->set('name', 'Neo');
        $driver->delete('name');

        self::assertFalse($driver->has('name'));
    }

    public function testClearRemovesAllKeys(): void
    {
        $driver = new ArrayDriver();
        $driver->set('a', 1);
        $driver->set('b', 2);

        $driver->clear();

        self::assertFalse($driver->has('a'));
        self::assertFalse($driver->has('b'));
    }
}