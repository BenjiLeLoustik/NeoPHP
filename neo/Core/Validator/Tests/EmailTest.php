<?php
declare(strict_types=1);

namespace Neo\Core\Validator\Tests;

use Neo\Core\Validator\Assert\Email;
use PHPUnit\Framework\TestCase;

final class EmailTest extends TestCase
{
    public function testNullAndEmptyStringAreValid(): void
    {
        $constraint = new Email();
        self::assertTrue($constraint->validate(null));
        self::assertTrue($constraint->validate(''));
    }

    public function testValidEmailPasses(): void
    {
        self::assertTrue(new Email()->validate('john.doe@example.com'));
    }

    public function testMissingAtSignFails(): void
    {
        self::assertFalse(new Email()->validate('not-an-email'));
    }

    public function testMissingDomainExtensionFails(): void
    {
        self::assertFalse(new Email()->validate('john@localhost'));
    }
}