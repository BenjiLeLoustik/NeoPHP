<?php
declare(strict_types=1);

namespace Neo\Core\Validator\Assert;

use Neo\Core\Validator\Abstract\AbstractConstraint;

#[\Attribute(\Attribute::TARGET_PROPERTY)]
class Url extends AbstractConstraint
{
    public function validate(mixed $value, ?object $object = null): bool
    {
        if ($value === null || $value === '') return true;
        return filter_var($value, FILTER_VALIDATE_URL) !== false;
    }
}
