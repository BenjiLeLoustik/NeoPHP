<?php
declare(strict_types=1);

namespace Neo\Core\Validator\Assert;

use Neo\Core\Validator\Abstract\AbstractConstraint;
use Neo\Core\Validator\Validator\ExistsValidator;

#[\Attribute(\Attribute::TARGET_PROPERTY)]
final class Exists extends AbstractConstraint
{
    /**
     * @param class-string $entity
     */
    public function __construct(
        public string $entity,
        public string $field,
        string $message = '',
    ) {
        parent::__construct($message);
    }

    public function validatedBy(): string
    {
        return ExistsValidator::class;
    }
}