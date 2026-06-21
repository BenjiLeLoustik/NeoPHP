<?php
declare(strict_types=1);

namespace Neo\Core\Extension\Tests;

use DateMalformedStringException;
use DateTimeImmutable;
use Neo\Core\Extension\Date\DateExtension;
use PHPUnit\Framework\TestCase;

final class DateExtensionTest extends TestCase
{
    private DateExtension $date;

    protected function setUp(): void
    {
        $this->date = new DateExtension();
    }

    /**
     * @throws DateMalformedStringException
     */
    public function testFormat(): void
    {
        self::assertSame('01/01/2024', $this->date->format('2024-01-01', 'd/m/Y'));
    }

    /**
     * @throws DateMalformedStringException
     */
    public function testDiffInDays(): void
    {
        self::assertSame(5, $this->date->diffInDays('2024-01-01', '2024-01-06'));
    }

    /**
     * @throws DateMalformedStringException
     */
    public function testDiffInHours(): void
    {
        self::assertSame(2, $this->date->diffInHours('2024-01-01 00:00:00', '2024-01-01 02:00:00'));
    }

    /**
     * @throws DateMalformedStringException
     */
    public function testDiffInMinutes(): void
    {
        self::assertSame(30, $this->date->diffInMinutes('2024-01-01 00:00:00', '2024-01-01 00:30:00'));
    }

    /**
     * @throws DateMalformedStringException
     */
    public function testAddDays(): void
    {
        $result = $this->date->addDays('2024-01-01', 5);
        self::assertSame('2024-01-06', $result->format('Y-m-d'));
    }

    /**
     * @throws DateMalformedStringException
     */
    public function testSubDays(): void
    {
        $result = $this->date->subDays('2024-01-10', 3);
        self::assertSame('2024-01-07', $result->format('Y-m-d'));
    }

    /**
     * @throws DateMalformedStringException
     */
    public function testAddMonths(): void
    {
        $result = $this->date->addMonths('2024-01-01', 2);
        self::assertSame('2024-03-01', $result->format('Y-m-d'));
    }

    /**
     * @throws DateMalformedStringException
     */
    public function testIsPast(): void
    {
        self::assertTrue($this->date->isPast('2000-01-01'));
        self::assertFalse($this->date->isPast('2099-01-01'));
    }

    /**
     * @throws DateMalformedStringException
     */
    public function testIsFuture(): void
    {
        self::assertTrue($this->date->isFuture('2099-01-01'));
        self::assertFalse($this->date->isFuture('2000-01-01'));
    }

    /**
     * @throws DateMalformedStringException
     */
    public function testIsWeekend(): void
    {
        self::assertTrue($this->date->isWeekend('2024-01-06'));
        self::assertFalse($this->date->isWeekend('2024-01-08'));
    }

    /**
     * @throws DateMalformedStringException
     */
    public function testIsLeapYear(): void
    {
        self::assertTrue($this->date->isLeapYear(2024));
        self::assertFalse($this->date->isLeapYear(2023));
    }

    /**
     * @throws DateMalformedStringException
     */
    public function testIsBetween(): void
    {
        self::assertTrue($this->date->isBetween('2024-06-15', '2024-06-01', '2024-06-30'));
        self::assertFalse($this->date->isBetween('2024-07-01', '2024-06-01', '2024-06-30'));
    }

    /**
     * @throws DateMalformedStringException
     */
    public function testAge(): void
    {
        $birthdate = new DateTimeImmutable()->modify('-30 years')->format('Y-m-d');
        self::assertSame(30, $this->date->age($birthdate));
    }

    /**
     * @throws DateMalformedStringException
     */
    public function testHumanDiff(): void
    {
        $ref = new DateTimeImmutable('2024-01-10');
        self::assertSame('5 days ago', $this->date->humanDiff('2024-01-05', $ref));
        self::assertSame('1 day ago', $this->date->humanDiff('2024-01-09', $ref));
    }

    /**
     * @throws DateMalformedStringException
     */
    public function testAddBusinessDaysSkipsWeekends(): void
    {
        $result = $this->date->addBusinessDays('2024-01-05', 1);
        self::assertSame('2024-01-08', $result->format('Y-m-d'));
    }
}