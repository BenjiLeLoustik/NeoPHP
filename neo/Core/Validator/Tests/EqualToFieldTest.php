<?php
declare(strict_types=1);

namespace Neo\Core\Validator\Tests;

use Neo\Core\Validator\Assert\EqualToField;
use PHPUnit\Framework\TestCase;

final class EqualToFieldTest extends TestCase
{
    public function testReturnsFalseWhenObjectIsNull(): void
    {
        self::assertFalse(new EqualToField('password')->validate('secret', null));
    }

    public function testReturnsFalseWhenFieldDoesNotExistOnObject(): void
    {
        $model = new \stdClass();
        $model->password = 'secret';

        self::assertFalse(new EqualToField('confirmation')->validate('secret', $model));
    }

    public function testReturnsTrueWhenValuesMatch(): void
    {
        $model = new \stdClass();
        $model->password = 'secret';

        self::assertTrue(new EqualToField('password')->validate('secret', $model));
    }

    public function testReturnsFalseWhenValuesDiffer(): void
    {
        $model = new \stdClass();
        $model->password = 'secret';

        self::assertFalse(new EqualToField('password')->validate('different', $model));
    }

    public function testComparisonIsStrict(): void
    {
        $model = new \stdClass();
        $model->count = 0;

        self::assertFalse(new EqualToField('count')->validate('0', $model));
    }
}