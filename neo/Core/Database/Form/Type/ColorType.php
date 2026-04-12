<?php
declare(strict_types=1);

namespace Neo\Core\Database\Form\Type;

use Neo\Core\Database\Form\FormField;

class ColorType extends AbstractType
{
    public ?string $type = 'color';

    public function render(FormField $field): string
    {
        $value = htmlspecialchars(
            (string)($field->getValue() ?? '#000000'),
            ENT_QUOTES
        );

        $name  = $field->getName();
        $id    = $field->getOption('id', $name);
        $autocomplete = $field->getOption('autocomplete', 'off');

        $attrs = '';
        foreach ($field->getOptions() as $k => $v) {
            if (!in_array($k, ['label', 'value'])) {
                $attrs .= " {$k}='{$v}'";
            }
        }

        return <<<HTML
<input type='color' name='{$name}' id='{$id}' value='{$value}' autocomplete='{$autocomplete}'{$attrs} />
HTML;
    }
}
