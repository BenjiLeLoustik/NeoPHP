<?php
declare(strict_types=1);

namespace Neo\Core\Profiler\Tests;

use Neo\Core\Profiler\Profiler;
use Neo\Core\Profiler\Tests\Fixture\Collectors\FakeCollector;
use PHPUnit\Framework\TestCase;

class ProfilerTest extends TestCase
{
    protected function setUp(): void
    {
        Profiler::reset();
    }

    protected function tearDown(): void
    {
        Profiler::reset();
    }

    public function testGetInstanceReturnsSameInstance(): void
    {
        self::assertSame(Profiler::getInstance(), Profiler::getInstance());
    }

    public function testResetCreatesNewInstance(): void
    {
        $first = Profiler::getInstance();
        Profiler::reset();
        $second = Profiler::getInstance();

        self::assertNotSame($first, $second);
    }

    public function testAddCollectorStoresItByName(): void
    {
        $profiler = Profiler::getInstance();
        $collector = new FakeCollector();

        $profiler->addCollector($collector);

        self::assertSame($collector, $profiler->getCollector('fake'));
        self::assertSame(['fake' => $collector], $profiler->getCollectors());
    }

    public function testGetCollectorReturnsNullWhenNotFound(): void
    {
        $profiler = Profiler::getInstance();

        self::assertNull($profiler->getCollector('missing'));
    }

    public function testCollectIncludesDurationMemoryAndCollectorData(): void
    {
        $profiler = Profiler::getInstance();
        $profiler->addCollector(new FakeCollector());

        $data = $profiler->collect();

        self::assertArrayHasKey('duration', $data);
        self::assertArrayHasKey('memory', $data);
        self::assertSame(['value' => 42], $data['fake']);
    }

    public function testGetPeakMemoryReturnsPositiveValue(): void
    {
        self::assertGreaterThan(0, Profiler::getInstance()->getPeakMemory());
    }

    public function testGetTotalDurationReturnsNonNegativeValue(): void
    {
        self::assertGreaterThanOrEqual(0.0, Profiler::getInstance()->getTotalDuration());
    }
}