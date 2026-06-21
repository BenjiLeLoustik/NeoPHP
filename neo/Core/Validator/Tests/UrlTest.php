<?php
declare(strict_types=1);

namespace Neo\Core\Validator\Tests;

use Neo\Core\Validator\Assert\Url;
use PHPUnit\Framework\TestCase;

final class UrlTest extends TestCase
{
    public function testNullAndEmptyStringAreValid(): void
    {
        $constraint = new Url();
        self::assertTrue($constraint->validate(null));
        self::assertTrue($constraint->validate(''));
    }

    public function testValidUrlPasses(): void
    {
        self::assertTrue(new Url()->validate('https://example.com/path?query=1'));
    }

    public function testInvalidUrlFails(): void
    {
        self::assertFalse(new Url()->validate('not a url'));
    }
}