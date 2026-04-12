<?php
declare(strict_types=1);

namespace Neo\Core\Database\Form\Type;

use Neo\Core\Database\Form\FormField;

class TextareaType extends AbstractType
{
    public ?string $type = 'textarea';

    public function render(FormField $field): string
    {
        $value = htmlspecialchars((string)($field->getValue() ?? ''), ENT_QUOTES);
        $name = $field->getName();
        $id = $field->getOption('id', $name);
        $rows = $field->getOption('rows', 5);
        $autocomplete = $field->getOption('autocomplete', 'off');

        $attrs = '';
        foreach ($field->getOptions() as $k => $v) {
            if (!in_array($k, ['label', 'value', 'rows'])) {
                $attrs .= " {$k}='{$v}'";
            }
        }

        return <<<HTML
<textarea name='{$name}' id='{$id}' rows='{$rows}' autocomplete='{$autocomplete}'{$attrs}>{$value}</textarea>
HTML;

    }
}
