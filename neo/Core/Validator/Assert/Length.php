<?php
declare(strict_types=1);

namespace Neo\Core\Validator\Assert;

use Neo\Core\Validator\Abstract\AbstractConstraint;

#[\Attribute(\Attribute::TARGET_PROPERTY)]
class Length extends AbstractConstraint
{
    public function __construct(
        public ?int $min = null,
        public ?int $max = null,
        public ?int $exactly = null,
        string $message = ""
    ) {
        if ($exactly) {
            $this->min = $exactly;
            $this->max = $exactly;
        }

        if ($message !== '') {
            $message = str_replace('{%min%}', $this->min !== null ? (string)$this->min : '∞', $message);
            $message = str_replace('{%max%}', $this->max !== null ? (string)$this->max : '∞', $message);
        }
        parent::__construct($message);
    }

    public function validate(mixed $value, ?object $object = null): bool
    {
        if ($value === null) return true;
        $len = mb_strlen((string)$value);

        if ($this->min !== null && $len < $this->min) return false;
        if ($this->max !== null && $len > $this->max) return false;

        return true;
    }
}
