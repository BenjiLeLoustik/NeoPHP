<?php
declare(strict_types=1);

namespace Neo\Core\Database\Form\Type;

use Neo\Core\Database\Form\FormField;

class TelType extends AbstractType
{
    public ?string $type = 'tel';

    public function render(FormField $field): string
    {
        $value = htmlspecialchars((string)($field->getValue() ?? ''), ENT_QUOTES);
        $name  = $field->getName();
        $id    = $field->getOption('id', $name);
        $autocomplete = $field->getOption('autocomplete', 'tel');

        $attrs = '';
        foreach ($field->getOptions() as $k => $v) {
            if (!in_array($k, ['label', 'value'])) {
                $attrs .= " {$k}='{$v}'";
            }
        }

        return <<<HTML
<input type="tel" name="{$name}" id="{$id}" value="{$value}" autocomplete="{$autocomplete}" {$attrs} />
HTML;

    }

    public function normalize(mixed $value, ?FormField $field = null): mixed
    {
        return is_string($value) ? str_replace(' ', '', trim($value)) : $value;
    }
}
