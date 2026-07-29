<?php
declare(strict_types=1);

namespace Neo\Core\Validator\Validator;

use Neo\Core\Validator\Interface\ConstraintInterface;
use Neo\Core\Validator\Interface\ConstraintValidatorInterface;
use Neo\Core\Validator\ValidationContext;

final class NotBlankValidator implements ConstraintValidatorInterface
{
    public function validate(mixed $value, ConstraintInterface $constraint, ValidationContext $context): void
    {
        $ok = match (true) {
            is_array($value)  => $value !== [],
            is_string($value) => trim($value) !== '',
            default           => $value !== null,
        };

        if (!$ok) {
            $context->addViolation($constraint->message ?: 'This field is required.');
        }
    }
}