<?php

declare(strict_types=1);

namespace Neo\Core\Validator\Assert;

use Neo\Core\Validator\Constraint;

#[\Attribute(\Attribute::TARGET_PROPERTY)]
class EqualToField extends Constraint
{
    public function __construct(
        public string $field,
        public string $message = ""
    ) {
        parent::__construct($message);
    }

    public function validate(mixed $value, ?object $object = null): bool
    {
        if (!is_object($object)) {
            return false;
        }

        if (!property_exists($object, $this->field)) {
            return false;
        }

        return $value === $object->{$this->field};
    }
}