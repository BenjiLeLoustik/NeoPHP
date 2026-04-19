<?php
declare(strict_types=1);

namespace Neo\Core\Database\Form\Type;

use Neo\Core\Database\Form\FormField;

class TextType extends AbstractType
{
    public ?string $type = 'text';

    public function render(FormField $field): string
    {
        $name = htmlspecialchars($field->getName(), ENT_QUOTES, 'UTF-8');
        $id = $this->getId($field);
        $value = htmlspecialchars((string)($field->getValue() ?? ''), ENT_QUOTES, 'UTF-8');
        $autocomplete = htmlspecialchars($field->getOption('autocomplete', 'off'), ENT_QUOTES, 'UTF-8');

        $attrs = $this->buildAttributes($this->collectAttrs($field));

        return sprintf(
            '<input type="text" name="%s" id="%s" value="%s" autocomplete="%s"%s />',
            $name,
            $id,
            $value,
            $autocomplete,
            $attrs
        );
    }
}
