<?php
declare(strict_types=1);

namespace Neo\Core\Validator\Tests;

use Neo\Core\Validator\Tests\Fixture\SimpleUserModel;
use Neo\Core\Validator\ValidatorManager;
use PHPUnit\Framework\TestCase;

final class ValidatorManagerTest extends TestCase
{
    public function testValidModelProducesNoErrors(): void
    {
        $model = new SimpleUserModel(
            name: 'John',
            email: 'john@example.com',
            password: 'secret',
            confirmPassword: 'secret',
        );

        $errors = (new ValidatorManager())->validate($model);

        self::assertSame([], $errors);
    }

    public function testBlankRequiredFieldProducesError(): void
    {
        $model = new SimpleUserModel(
            name: '',
            email: 'john@example.com',
            password: 'secret',
            confirmPassword: 'secret',
        );

        $errors = (new ValidatorManager())->validate($model);

        self::assertSame(['Name is required'], $errors['name']);
    }

    public function testNotBlankFailureStopsFurtherConstraintsOnSameField(): void
    {
        $model = new SimpleUserModel(name: '');

        $errors = (new ValidatorManager())->validate($model);

        self::assertCount(1, $errors['name']);
        self::assertSame(['Name is required'], $errors['name']);
    }

    public function testNonBlankFieldStillRunsSubsequentConstraints(): void
    {
        $model = new SimpleUserModel(name: 'Jo');

        $errors = (new ValidatorManager())->validate($model);

        self::assertSame(['Name is too short'], $errors['name']);
    }

    public function testInvalidEmailProducesError(): void
    {
        $model = new SimpleUserModel(name: 'John', email: 'not-an-email');

        $errors = (new ValidatorManager())->validate($model);

        self::assertSame(['Invalid email address'], $errors['email']);
    }

    public function testEmptyOptionalFieldIsSkipped(): void
    {
        $model = new SimpleUserModel(name: 'John', email: '');

        $errors = (new ValidatorManager())->validate($model);

        self::assertArrayNotHasKey('email', $errors);
    }

    public function testEqualToFieldDetectsMismatch(): void
    {
        $model = new SimpleUserModel(
            name: 'John',
            password: 'secret',
            confirmPassword: 'different',
        );

        $errors = (new ValidatorManager())->validate($model);

        self::assertSame(['Passwords do not match'], $errors['confirmPassword']);
    }

    public function testEqualToFieldPassesWhenValuesMatch(): void
    {
        $model = new SimpleUserModel(
            name: 'John',
            password: 'secret',
            confirmPassword: 'secret',
        );

        $errors = (new ValidatorManager())->validate($model);

        self::assertArrayNotHasKey('confirmPassword', $errors);
    }

    public function testFieldWithoutConstraintsNeverAppearsInErrors(): void
    {
        $model = new SimpleUserModel(name: 'John', password: 'secret', confirmPassword: 'secret');

        $errors = (new ValidatorManager())->validate($model);

        self::assertArrayNotHasKey('notes', $errors);
    }

    public function testMultipleFieldsCanFailIndependently(): void
    {
        $model = new SimpleUserModel(
            name: '',
            email: 'bad-email',
            password: 'secret',
            confirmPassword: 'nope',
        );

        $errors = (new ValidatorManager())->validate($model);

        self::assertArrayHasKey('name', $errors);
        self::assertArrayHasKey('email', $errors);
        self::assertArrayHasKey('confirmPassword', $errors);
    }
}