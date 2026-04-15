<?php
declare(strict_types=1);

namespace Neo\Core\Database\Form\Type;

use Neo\Core\Database\Form\FormField;

class EmailType extends AbstractType
{
    public ?string $type = 'email';

    public function render(FormField $field): string
    {
        $name = htmlspecialchars($field->getName(), ENT_QUOTES, 'UTF-8');
        $id = htmlspecialchars($field->getOption('id', $field->getName()), ENT_QUOTES, 'UTF-8');
        $value = htmlspecialchars((string)($field->getValue() ?? ''), ENT_QUOTES, 'UTF-8');
        $autocomplete = htmlspecialchars($field->getOption('autocomplete', 'email'), ENT_QUOTES, 'UTF-8');

        $attrs = $this->buildAttributes($this->collectAttrs($field));

        return <<<HTML
<input type="email" name="{$name}" id="{$id}" value="{$value}" autocomplete="{$autocomplete}"{$attrs} />
HTML;
    }

    public function normalize(mixed $value, ?FormField $field = null): mixed
    {
        return is_string($value) ? trim($value) : $value;
    }
}
