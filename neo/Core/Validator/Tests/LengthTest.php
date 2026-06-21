<?php
declare(strict_types=1);

namespace Neo\Core\Validator\Tests;

use Neo\Core\Validator\Assert\Length;
use PHPUnit\Framework\TestCase;

final class LengthTest extends TestCase
{
    public function testNullIsValid(): void
    {
        self::assertTrue(new Length(min: 3)->validate(null));
    }

    public function testTooShortFailsMin(): void
    {
        self::assertFalse(new Length(min: 3)->validate('ab'));
    }

    public function testExactMinLengthPasses(): void
    {
        self::assertTrue(new Length(min: 3)->validate('abc'));
    }

    public function testTooLongFailsMax(): void
    {
        self::assertFalse(new Length(max: 5)->validate('abcdef'));
    }

    public function testWithinMinAndMaxPasses(): void
    {
        self::assertTrue(new Length(min: 2, max: 5)->validate('abcd'));
    }

    public function testExactlySetsBothMinAndMax(): void
    {
        $constraint = new Length(exactly: 4);

        self::assertTrue($constraint->validate('abcd'));
        self::assertFalse($constraint->validate('abc'));
        self::assertFalse($constraint->validate('abcde'));
    }

    public function testMultibyteStringIsCountedByCharacterNotByte(): void
    {
        self::assertTrue(new Length(exactly: 4)->validate('café'));
    }

    public function testMessagePlaceholdersAreReplaced(): void
    {
        $constraint = new Length(min: 3, max: 10, message: 'Between {%min%} and {%max%} characters');

        self::assertSame('Between 3 and 10 characters', $constraint->message);
    }

    public function testMessagePlaceholderUsesInfinitySymbolWhenBoundIsNull(): void
    {
        $constraint = new Length(min: 3, message: 'At least {%min%}, at most {%max%}');

        self::assertSame('At least 3, at most ∞', $constraint->message);
    }
}