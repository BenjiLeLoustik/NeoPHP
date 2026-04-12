<?php
declare(strict_types=1);

namespace Neo\Core\Database\Form\Type;

use Neo\Core\Database\Form\FormField;

class CheckboxType extends AbstractType
{
    public ?string $type = 'checkbox';

    public function render(FormField $field): string
    {
        $name = $field->getName();
        $id = $field->getOption('id', $name);
        $value = $field->getValue() ?? 0;
        $checked = $value ? 'checked' : '';

        $attrs = '';
        foreach ($field->getOptions() as $k => $v) {
            if (!in_array($k, ['label', 'value'])) {
                $attrs .= " {$k}='{$v}'";
            }
        }

        return <<<HTML
<input type="hidden" name="{$name}" value="0" />
<input type="checkbox" name="{$name}" id="{$id}" value="1" {$checked} {$attrs} />
HTML;
    }

    public function normalize(mixed $value, ?FormField $field = null): mixed
    {
        return $value ? 1 : 0;
    }
}
