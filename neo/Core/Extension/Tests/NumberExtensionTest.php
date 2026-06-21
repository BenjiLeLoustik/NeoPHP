<?php
declare(strict_types=1);

namespace Neo\Core\Extension\Tests;

use Neo\Core\Extension\Number\NumberExtension;
use PHPUnit\Framework\TestCase;

final class NumberExtensionTest extends TestCase
{
    private NumberExtension $num;

    protected function setUp(): void
    {
        $this->num = new NumberExtension();
    }

    public function testFormat(): void
    {
        self::assertSame('1,234.57', $this->num->format(1234.567));
    }

    public function testCurrency(): void
    {
        self::assertSame('$10.00', $this->num->currency(10));
    }

    public function testPercent(): void
    {
        self::assertSame('50.00%', $this->num->percent(1, 2));
    }

    public function testPercentWithZeroTotal(): void
    {
        self::assertSame('0%', $this->num->percent(1, 0));
    }

    public function testOrdinal(): void
    {
        self::assertSame('1st', $this->num->ordinal(1));
        self::assertSame('2nd', $this->num->ordinal(2));
        self::assertSame('3rd', $this->num->ordinal(3));
        self::assertSame('4th', $this->num->ordinal(4));
        self::assertSame('11th', $this->num->ordinal(11));
    }

    public function testClamp(): void
    {
        self::assertSame(5, $this->num->clamp(5, 1, 10));
        self::assertSame(1, $this->num->clamp(-5, 1, 10));
        self::assertSame(10, $this->num->clamp(99, 1, 10));
    }

    public function testIsBetween(): void
    {
        self::assertTrue($this->num->isBetween(5, 1, 10));
        self::assertFalse($this->num->isBetween(11, 1, 10));
    }

    public function testIsEvenAndOdd(): void
    {
        self::assertTrue($this->num->isEven(4));
        self::assertTrue($this->num->isOdd(3));
    }

    public function testToRoman(): void
    {
        self::assertSame('XIV', $this->num->toRoman(14));
        self::assertSame('XLII', $this->num->toRoman(42));
    }

    public function testFromRoman(): void
    {
        self::assertSame(14, $this->num->fromRoman('XIV'));
        self::assertSame(42, $this->num->fromRoman('XLII'));
    }

    public function testCelsiusToFahrenheit(): void
    {
        self::assertSame(32.0, $this->num->celsiusToFahrenheit(0));
        self::assertSame(212.0, $this->num->celsiusToFahrenheit(100));
    }

    public function testKmToMiles(): void
    {
        self::assertSame(0.6214, $this->num->kmToMiles(1));
    }

    public function testHumanSize(): void
    {
        self::assertSame('1.00 KB', $this->num->humanSize(1024));
    }
}