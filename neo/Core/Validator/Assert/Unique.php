<?php
declare(strict_types=1);

namespace Neo\Core\Validator\Assert;

use Neo\Core\Validator\Abstract\AbstractConstraint;
use Neo\Core\Validator\Validator\UniqueValidator;

#[\Attribute(\Attribute::TARGET_PROPERTY)]
final class Unique extends AbstractConstraint
{
    /**
     * @param array<string, mixed> $conditions
     */
    public function __construct(
        string $message = '',
        public ?string $field = null,
        public array $conditions = [],
    ) {
        parent::__construct($message);
    }

    public function validatedBy(): string
    {
        return UniqueValidator::class;
    }
}