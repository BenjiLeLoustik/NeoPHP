<?php
declare(strict_types=1);

namespace Neo\Core\Extension\Tests\Number;

use Neo\Core\Extension\Number\NumberExtension;
use PHPUnit\Framework\TestCase;

class NumberExtensionTest extends TestCase
{
    private NumberExtension $extension;

    protected function setUp(): void
    {
        $this->extension = new NumberExtension();
    }

    public function testFormatAndCurrencyAndPercent(): void
    {
        self::assertSame('1,234.56', $this->extension->format(1234.56));
        self::assertSame('€1,234.50', $this->extension->currency(1234.5, '€'));
        self::assertSame('25.00%', $this->extension->percent(25, 100));
        self::assertSame('0%', $this->extension->percent(25, 0));
    }

    public function testOrdinalAndParity(): void
    {
        self::assertSame('1st', $this->extension->ordinal(1));
        self::assertSame('2nd', $this->extension->ordinal(2));
        self::assertSame('3rd', $this->extension->ordinal(3));
        self::assertSame('11th', $this->extension->ordinal(11));

        self::assertTrue($this->extension->isEven(2));
        self::assertTrue($this->extension->isOdd(3));
    }

    public function testRomanConversions(): void
    {
        self::assertSame('MCMXCIV', $this->extension->toRoman(1994));
        self::assertSame(1994, $this->extension->fromRoman('MCMXCIV'));
    }
}