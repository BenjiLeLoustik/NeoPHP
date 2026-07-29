<?php
declare(strict_types=1);

namespace Neo\Core\Validator\Validator;

use Neo\Core\Validator\Interface\ConstraintInterface;
use Neo\Core\Validator\Interface\ConstraintValidatorInterface;
use Neo\Core\Validator\ValidationContext;

final class UrlValidator implements ConstraintValidatorInterface
{
    public function validate(mixed $value, ConstraintInterface $constraint, ValidationContext $context): void
    {
        if (filter_var($value, FILTER_VALIDATE_URL) === false) {
            $context->addViolation($constraint->message ?: 'This value is not a valid URL.');
        }
    }
}