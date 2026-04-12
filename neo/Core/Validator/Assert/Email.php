<?php
declare(strict_types=1);

namespace Neo\Core\Validator\Assert;

use Neo\Core\Validator\Constraint;

#[\Attribute(\Attribute::TARGET_PROPERTY)]
class Email extends Constraint
{
    public function validate(mixed $value, ?object $object = null): bool
    {
        if ($value === null || $value === '') return true;

        return filter_var($value, FILTER_VALIDATE_EMAIL) !== false
            && preg_match('/^[^@]+@[a-zA-Z0-9\-]+(\.[a-zA-Z]{2,})+$/', $value) === 1;
    }
}
