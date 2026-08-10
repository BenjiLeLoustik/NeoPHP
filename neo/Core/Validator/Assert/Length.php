<?php
declare(strict_types=1);

namespace Neo\Core\Validator\Assert;

use Neo\Core\Validator\Abstract\AbstractConstraint;
use Neo\Core\Validator\Validator\LengthValidator;

#[\Attribute(\Attribute::TARGET_PROPERTY)]
final class Length extends AbstractConstraint
{
    public function __construct(
        public ?int $min = null,
        public ?int $max = null,
        public ?int $exactly = null,
        string $message = '',
    ) {
        if ($exactly !== null) {
            $this->min = $exactly;
            $this->max = $exactly;
        }

        if ($message !== '') {
            $message = str_replace('{%min%}', $this->min !== null
                ? (string) $this->min
                : '∞', $message
            );
            $message = str_replace('{%max%}', $this->max !== null
                ? (string) $this->max
                : '∞', $message
            );
        }

        parent::__construct($message);
    }

    public function validatedBy(): string
    {
        return LengthValidator::class;
    }
}