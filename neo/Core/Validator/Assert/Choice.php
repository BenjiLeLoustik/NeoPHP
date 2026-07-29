<?php
declare(strict_types=1);

namespace Neo\Core\Validator\Assert;

use Neo\Core\Validator\Abstract\AbstractConstraint;
use Neo\Core\Validator\Validator\ChoiceValidator;

#[\Attribute(\Attribute::TARGET_PROPERTY)]
final class Choice extends AbstractConstraint
{
    /**
     * @param array<int|string, mixed> $choices
     */
    public function __construct(
        public array $choices,
        string $message = '',
    ) {
        parent::__construct($message);
    }

    public function validatedBy(): string
    {
        return ChoiceValidator::class;
    }
}