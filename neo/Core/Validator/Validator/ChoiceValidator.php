<?php
declare(strict_types=1);

namespace Neo\Core\Validator\Validator;

use Neo\Core\Validator\Assert\Choice;
use Neo\Core\Validator\Interface\ConstraintInterface;
use Neo\Core\Validator\Interface\ConstraintValidatorInterface;
use Neo\Core\Validator\ValidationContext;

final class ChoiceValidator implements ConstraintValidatorInterface
{
    public function validate(mixed $value, ConstraintInterface $constraint, ValidationContext $context): void
    {
        if (!$constraint instanceof Choice) {
            return;
        }

        if (!$this->isValid($value, $constraint->choices)) {
            $context->addViolation($constraint->getMessage() ?: 'This value is not a valid choice.');
        }
    }

    /**
     * @param array<int|string, mixed> $choices
     */
    private function isValid(mixed $value, array $choices): bool
    {
        if (is_int($value) || is_string($value)) {
            if (array_key_exists($value, $choices)) {
                return true;
            }
            if (is_numeric($value) && array_key_exists((int) $value, $choices)) {
                return false;
            }
        }

        return in_array($value, $choices, false);
    }
}
