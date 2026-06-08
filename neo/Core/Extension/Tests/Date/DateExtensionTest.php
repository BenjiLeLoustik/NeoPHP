<?php
declare(strict_types=1);

namespace Neo\Core\Extension\Tests\Date;

use DateTimeImmutable;
use Neo\Core\Extension\Date\DateExtension;
use PHPUnit\Framework\TestCase;

class DateExtensionTest extends TestCase
{
    private DateExtension $extension;

    protected function setUp(): void
    {
        $this->extension = new DateExtension();
    }

    public function testNowReturnsDateTimeImmutableWithTimezone(): void
    {
        $dt = $this->extension->now('Europe/Paris');
        self::assertInstanceOf(DateTimeImmutable::class, $dt);
        self::assertSame('Europe/Paris', $dt->getTimezone()->getName());
    }

    public function testParseAndFormatAndTimestampConversion(): void
    {
        $dateStr = '2026-06-08 14:30:00';
        $dt = $this->extension->parse($dateStr);

        self::assertInstanceOf(DateTimeImmutable::class, $dt);
        self::assertSame('08/06/2026', $this->extension->format($dt));
        self::assertSame('08/06/2026', $this->extension->format($dateStr));

        $ts = $this->extension->toTimestamp($dt);
        self::assertSame($ts, $this->extension->toTimestamp($dateStr));

        $fromTs = $this->extension->fromTimestamp($ts);
        self::assertSame($ts, $fromTs->getTimestamp());

        self::assertFalse($this->extension->parse('invalid-date', 'Y-m-d'));
    }

    public function testBusinessDaysCalculations(): void
    {
        $start = '2026-06-05';
        $result = $this->extension->addBusinessDays($start, 2);
        self::assertSame('2026-06-09', $result->format('Y-m-d'));

        $count = $this->extension->countBusinessDays('2026-06-05', '2026-06-08');
        self::assertSame(2, $count);
    }
}