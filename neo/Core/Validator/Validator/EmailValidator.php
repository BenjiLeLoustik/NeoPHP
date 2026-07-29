<?php
declare(strict_types=1);

namespace Neo\Core\Validator\Validator;

use Neo\Core\Validator\Interface\ConstraintInterface;
use Neo\Core\Validator\Interface\ConstraintValidatorInterface;
use Neo\Core\Validator\ValidationContext;

final class EmailValidator implements ConstraintValidatorInterface
{
    public function validate(mixed $value, ConstraintInterface $constraint, ValidationContext $context): void
    {
        $valid = filter_var($value, FILTER_VALIDATE_EMAIL) !== false
            && preg_match('/^[^@]+@[a-zA-Z0-9\-]+(\.[a-zA-Z]{2,})+$/', (string) $value) === 1;

        if (!$valid) {
            $context->addViolation($constraint->getMessage() ?: 'This value is not a valid email address.');
        }
    }
}
