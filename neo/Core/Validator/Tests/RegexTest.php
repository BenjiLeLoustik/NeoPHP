<?php
declare(strict_types=1);

namespace Neo\Core\Validator\Tests;

use Neo\Core\Validator\Assert\Regex;
use PHPUnit\Framework\TestCase;

final class RegexTest extends TestCase
{
    public function testNullIsValid(): void
    {
        self::assertTrue(new Regex('/^[0-9]+$/')->validate(null));
    }

    public function testMatchingValuePasses(): void
    {
        self::assertTrue(new Regex('/^[0-9]+$/')->validate('12345'));
    }

    public function testNonMatchingValueFails(): void
    {
        self::assertFalse(new Regex('/^[0-9]+$/')->validate('12a45'));
    }

    public function testValueIsCastToString(): void
    {
        self::assertTrue(new Regex('/^[0-9]+$/')->validate(12345));
    }
}