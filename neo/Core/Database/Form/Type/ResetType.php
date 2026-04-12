<?php
declare(strict_types=1);

namespace Neo\Core\Database\Form\Type;

use Neo\Core\Database\Form\FormField;

class ResetType extends AbstractType
{
    public ?string $type = 'reset';

    public function render(FormField $field): string
    {
        $label = $field->getOption('label', 'Reset');
        $name  = $field->getName();
        $id    = $field->getOption('id', $name);

        $attrs = '';
        foreach ($field->getOptions() as $k => $v) {
            if (!in_array($k, ['label', 'value'])) {
                $attrs .= " {$k}='{$v}'";
            }
        }

        return <<<HTML
<button type='reset' name='{$name}' id='{$id}'{$attrs}>{$label}</button>
HTML;
    }

    public function normalize(mixed $value, ?FormField $field = null): mixed
    {
        return null;
    }
}
