<?php
declare(strict_types=1);

namespace Neo\Core\Database\Form\Type;

use Neo\Core\Database\Form\FormField;

class SubmitType extends AbstractType
{
    public ?string $type = 'submit';

    public function render(FormField $field): string
    {
        $name = htmlspecialchars($field->getName(), ENT_QUOTES, 'UTF-8');
        $id = htmlspecialchars($field->getOption('id', $field->getName()), ENT_QUOTES, 'UTF-8');
        $label = htmlspecialchars($field->getOption('label', 'Submit'), ENT_QUOTES, 'UTF-8');
        $attrs = $this->buildAttributes($this->collectAttrs($field));

        return <<<HTML
<button type="submit" name="{$name}" id="{$id}"{$attrs}>{$label}</button>
HTML;
    }

    public function normalize(mixed $value, ?FormField $field = null): mixed
    {
        return null;
    }
}
