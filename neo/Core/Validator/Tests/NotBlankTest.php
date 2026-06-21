<?php
declare(strict_types=1);

namespace Neo\Core\Validator\Tests;

use Neo\Core\Validator\Assert\NotBlank;
use PHPUnit\Framework\TestCase;

final class NotBlankTest extends TestCase
{
    public function testNullIsInvalid(): void
    {
        self::assertFalse(new NotBlank()->validate(null));
    }

    public function testEmptyStringIsInvalid(): void
    {
        self::assertFalse(new NotBlank()->validate(''));
    }

    public function testWhitespaceOnlyStringIsInvalid(): void
    {
        self::assertFalse(new NotBlank()->validate('   '));
    }

    public function testNonEmptyStringIsValid(): void
    {
        self::assertTrue(new NotBlank()->validate('hello'));
    }

    public function testEmptyArrayIsInvalid(): void
    {
        self::assertFalse(new NotBlank()->validate([]));
    }

    public function testNonEmptyArrayIsValid(): void
    {
        self::assertTrue(new NotBlank()->validate([0]));
    }

    public function testZeroIsConsideredValid(): void
    {
        self::assertTrue(new NotBlank()->validate(0));
        self::assertTrue(new NotBlank()->validate(false));
    }
}