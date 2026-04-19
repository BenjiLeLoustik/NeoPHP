<?php
declare(strict_types=1);

namespace Neo\Core\Database\Form\Type;

use Neo\Core\Database\Form\FormField;

class TextareaType extends AbstractType
{
    public ?string $type = 'textarea';

    public function render(FormField $field): string
    {
        $name = htmlspecialchars($field->getName(), ENT_QUOTES, 'UTF-8');
        $id = $this->getId($field);
        $value = htmlspecialchars((string)($field->getValue() ?? ''), ENT_QUOTES, 'UTF-8');
        $rows = htmlspecialchars((string)$field->getOption('rows', 5), ENT_QUOTES, 'UTF-8');
        $autocomplete = htmlspecialchars($field->getOption('autocomplete', 'off'), ENT_QUOTES, 'UTF-8');

        $attrs = $this->buildAttributes($this->collectAttrs($field, ['rows']));

        return <<<HTML
<textarea name="{$name}" id="{$id}" rows="{$rows}" autocomplete="{$autocomplete}"{$attrs}>{$value}</textarea>
HTML;
    }
}
