<?php
declare(strict_types=1);

namespace Neo\Core\Database\Form\Type;

use Neo\Core\Database\Form\FormField;

class TextType extends AbstractType
{
    public ?string $type = 'text';

    public function render(FormField $field): string
    {
        $escape = fn ($v) => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');

        $name = $escape($field->getName());
        $id = $escape($field->getOption('id', $field->getName()));
        $value = $escape($field->getValue() ?? '');
        $autocomplete = $escape($field->getOption('autocomplete', 'off'));

        $attrs = '';
        foreach ($field->getOptions() as $k => $v) {
            if (in_array($k, ['label', 'value', 'id', 'autocomplete'], true)) {
                continue;
            }

            $attrs .= ' ' . $escape($k) . '="' . $escape($v) . '"';
        }

        return sprintf(
            '<input type="text" name="%s" id="%s" value="%s" autocomplete="%s"%s>',
            $name,
            $id,
            $value,
            $autocomplete,
            $attrs
        );
    }
}
