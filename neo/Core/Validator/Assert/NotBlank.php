<?php
declare(strict_types=1);

namespace Neo\Core\Validator\Assert;

use Neo\Core\Validator\Constraint;

#[\Attribute(\Attribute::TARGET_PROPERTY)]
class NotBlank extends Constraint
{
    public function validate(mixed $value, ?object $object = null): bool
    {
        if (is_array($value)) {
            return !empty($value);
        }

        if (is_string($value)) {
            return trim($value) !== '';
        }

        return $value !== null;
    }
}
