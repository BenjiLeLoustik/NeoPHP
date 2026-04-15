<?php
declare(strict_types=1);

namespace Neo\Core\Database\Form\Type;

use Neo\Core\Database\Form\FormField;

class SelectType extends AbstractType
{
    public ?string $type = 'select';

    public function render(FormField $field): string
    {
        $name = htmlspecialchars($field->getName(), ENT_QUOTES, 'UTF-8');
        $id = htmlspecialchars($field->getOption('id', $field->getName()), ENT_QUOTES, 'UTF-8');
        $autocomplete = htmlspecialchars($field->getOption('autocomplete', 'off'), ENT_QUOTES, 'UTF-8');

        $value = $field->getValue();
        $choices = $field->getOption('choices', []);

        $currentValue = ($value !== null && $value !== '') ? $value : null;

        $attrs = $this->buildAttributes(
            $this->collectAttrs($field, ['choices', 'placeholder', 'default'])
        );

        $placeholderOption = '';
        $placeholder = $field->getOption('placeholder');
        if ($placeholder) {
            $phEsc = htmlspecialchars($placeholder, ENT_QUOTES, 'UTF-8');
            $phSelected = $currentValue === null ? ' selected' : '';
            $placeholderOption = "<option value=\"\"{$phSelected}>{$phEsc}</option>\n";
        }

        $optionsHtml = '';
        foreach ($choices as $val => $label) {
            $valEsc = htmlspecialchars((string) $val, ENT_QUOTES, 'UTF-8');
            $labelEsc = htmlspecialchars((string) $label, ENT_QUOTES, 'UTF-8');
            $selected = ($currentValue !== null && (string) $currentValue === (string) $val)
                ? ' selected'
                : '';
            $optionsHtml .= "<option value=\"{$valEsc}\"{$selected}>{$labelEsc}</option>\n";
        }

        return <<<HTML
<select name="{$name}" id="{$id}" autocomplete="{$autocomplete}"{$attrs}>
{$placeholderOption}{$optionsHtml}</select>
HTML;
    }

    public function normalize(mixed $value, ?FormField $field = null): mixed
    {
        if ($value === '' || $value === null) {
            return null;
        }

        return $value;
    }
}
