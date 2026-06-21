<?php
declare(strict_types=1);

namespace Neo\Core\Validator\Assert;

use Neo\Core\Validator\Abstract\AbstractConstraint;

#[\Attribute(\Attribute::TARGET_PROPERTY)]
class Regex extends AbstractConstraint
{
    public function __construct(
        public string $pattern,
        string $message = ""
    ) {
        parent::__construct($message);
    }

    public function validate(mixed $value, ?object $object = null): bool
    {
        if ($value === null) return true;
        return preg_match($this->pattern, (string)$value) === 1;
    }
}
