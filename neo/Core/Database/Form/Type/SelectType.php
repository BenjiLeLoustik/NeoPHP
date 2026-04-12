<?php
declare(strict_types=1);

namespace Neo\Core\Database\Form\Type;

use Neo\Core\Database\Form\FormField;

class SelectType extends AbstractType
{
    public ?string $type = 'select';

    public function render(FormField $field): string
    {
        $name         = htmlspecialchars($field->getName());
        $id           = htmlspecialchars($field->getOption('id', $name));
        $value        = $field->getValue();
        $default      = $field->getOption('default', null);
        $choices      = $field->getOption('choices', []);
        $placeholder  = $field->getOption('placeholder');
        $autocomplete = htmlspecialchars($field->getOption('autocomplete', 'off'));

        $ignored = ['label', 'value', 'choices', 'placeholder', 'default'];
        $attrs = '';
        foreach ($field->getOptions() as $k => $v) {
            if (!in_array($k, $ignored, true)) {
                $v = htmlspecialchars((string) $v);
                $attrs .= " {$k}=\"{$v}\"";
            }
        }

        $currentValue = ($value !== null && $value !== '') ? $value : null;

        $placeholderOption = '';
        if ($placeholder) {
            $phEsc = htmlspecialchars($placeholder);
            $phSelected = $currentValue === null ? 'selected' : '';
            $placeholderOption = "<option value=\"\" {$phSelected}>{$phEsc}</option>";
        }

        $optionsHtml = '';
        foreach ($choices as $val => $label) {
            $valEsc   = htmlspecialchars((string) $val);
            $labelEsc = htmlspecialchars((string) $label);

            $selected = ($currentValue !== null && (string)$currentValue === (string)$val)
                ? 'selected'
                : '';

            $optionsHtml .= "<option value=\"{$valEsc}\" {$selected}>{$labelEsc}</option>";
        }

        return "<select name=\"{$name}\" id=\"{$id}\" autocomplete=\"{$autocomplete}\"{$attrs}>
{$placeholderOption}
{$optionsHtml}
</select>";
    }

    public function normalize(mixed $value, ?FormField $field = null): mixed
    {
        if ($value === '' || $value === null) {
            return null;
        }

        return $value;
    }
}
