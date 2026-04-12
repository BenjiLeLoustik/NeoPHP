<?php
declare(strict_types=1);

namespace Neo\Core\Validator;

#[\Attribute(\Attribute::TARGET_PROPERTY)]
abstract class Constraint
{
    public string $message;
    protected ?string $resolvedPropertyName = null;

    public function __construct(string $message = '')
    {
        $this->message = $message;
    }

    public function setPropertyName(string $name): void
    {
        $this->resolvedPropertyName = $name;
    }

    abstract public function validate(mixed $value, ?object $object = null): bool;
}
