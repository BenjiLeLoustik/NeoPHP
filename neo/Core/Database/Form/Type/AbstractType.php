<?php
declare(strict_types=1);

namespace Neo\Core\Database\Form\Type;

use Neo\Core\Database\Form\FormField;

abstract class AbstractType
{
    public ?string $type = null;

    abstract public function render(FormField $field): string;

    public function normalize(mixed $value, ?FormField $field = null): mixed
    {
        return $value;
    }
}