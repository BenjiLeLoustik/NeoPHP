<?php
declare(strict_types=1);

namespace Neo\Core\Validator\Abstract;

abstract class AbstractConstraint
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
