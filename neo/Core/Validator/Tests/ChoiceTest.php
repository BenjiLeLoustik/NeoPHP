<?php
declare(strict_types=1);

namespace Neo\Core\Validator\Tests;

use Neo\Core\Validator\Assert\Choice;
use PHPUnit\Framework\TestCase;

final class ChoiceTest extends TestCase
{
    public function testNullIsValid(): void
    {
        self::assertTrue(new Choice(['a' => 'Apple', 'b' => 'Banana'])->validate(null));
    }

    public function testMatchingKeyIsValid(): void
    {
        self::assertTrue(new Choice(['a' => 'Apple', 'b' => 'Banana'])->validate('a'));
    }

    public function testUnknownKeyIsInvalid(): void
    {
        self::assertFalse(new Choice(['a' => 'Apple', 'b' => 'Banana'])->validate('z'));
    }

    public function testMatchingValueByLooseComparisonIsValid(): void
    {
        self::assertTrue(new Choice([1 => 'Apple', 2 => 'Banana'])->validate('Apple'));
    }
}