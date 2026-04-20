<?php
declare(strict_types=1);

namespace Neo\Core\Database\Form\Type;

use Neo\Core\Database\Form\FormField;

class ResetType extends AbstractType
{
    public ?string $type = 'reset';

    public function render(FormField $field): string
    {
        $name = htmlspecialchars($field->getName(), ENT_QUOTES, 'UTF-8');
        $id = $this->getId($field);
        $label = htmlspecialchars($field->getOption('label', 'Reset'), ENT_QUOTES, 'UTF-8');
        $attrs = $this->buildAttributes($this->collectAttrs($field));

        return <<<HTML
<button type="reset" name="{$name}" id="{$id}"{$attrs}>{$label}</button>
HTML;
    }

    public function normalize(mixed $value, ?FormField $field = null): mixed
    {
        return null;
    }
}
