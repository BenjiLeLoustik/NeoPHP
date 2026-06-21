<?php
declare(strict_types=1);

namespace Neo\Core\Validator\Tests;

use Neo\Core\Validator\Assert\Date;
use PHPUnit\Framework\TestCase;

final class DateTest extends TestCase
{
    public function testNullAndEmptyStringAreValid(): void
    {
        $constraint = new Date();
        self::assertTrue($constraint->validate(null));
        self::assertTrue($constraint->validate(''));
    }

    public function testValidDateMatchingDefaultFormatPasses(): void
    {
        self::assertTrue(new Date()->validate('2024-06-15'));
    }

    public function testDateNotMatchingFormatFails(): void
    {
        self::assertFalse(new Date()->validate('15/06/2024'));
    }

    public function testCompletelyInvalidStringFails(): void
    {
        self::assertFalse(new Date()->validate('not-a-date'));
    }

    public function testCustomFormatIsRespected(): void
    {
        self::assertTrue(new Date(format: 'd/m/Y')->validate('15/06/2024'));
    }

    public function testDateBeforeMinFails(): void
    {
        $constraint = new Date(min: '2024-01-01');
        self::assertFalse($constraint->validate('2023-12-31'));
    }

    public function testDateOnMinBoundaryPasses(): void
    {
        $constraint = new Date(min: '2024-01-01');
        self::assertTrue($constraint->validate('2024-01-01'));
    }

    public function testDateAfterMaxFails(): void
    {
        $constraint = new Date(max: '2024-12-31');
        self::assertFalse($constraint->validate('2025-01-01'));
    }

    public function testDateWithinMinMaxRangePasses(): void
    {
        $constraint = new Date(min: '2024-01-01', max: '2024-12-31');
        self::assertTrue($constraint->validate('2024-06-15'));
    }

    public function testDateTimeInterfaceValueIsAccepted(): void
    {
        $constraint = new Date(min: '2024-01-01');
        self::assertTrue($constraint->validate(new \DateTimeImmutable('2024-06-15')));
    }
}