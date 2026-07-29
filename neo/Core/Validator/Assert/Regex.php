<?php
declare(strict_types=1);

namespace Neo\Core\Validator\Assert;

use Neo\Core\Validator\Abstract\AbstractConstraint;
use Neo\Core\Validator\Validator\RegexValidator;

#[\Attribute(\Attribute::TARGET_PROPERTY)]
final class Regex extends AbstractConstraint
{
    public function __construct(
        public string $pattern,
        string $message = '',
    ) {
        parent::__construct($message);
    }

    public function validatedBy(): string
    {
        return RegexValidator::class;
    }
}