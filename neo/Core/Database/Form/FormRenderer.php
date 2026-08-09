<?php
declare(strict_types=1);

namespace Neo\Core\Database\Form;

use DateTimeInterface;
use Neo\Core\Database\Form\Field\FieldType;
use Neo\Core\Database\Form\Field\FormField;

final class FormRenderer
{
    /**
     * @param array<string, string> $attributes
     */
    public function render(
        Form $form,
        string $action = '',
        string $method = 'POST',
        array $attributes = []
    ): string {
        $html = $this->start($form, $action, $method, $attributes);

        foreach ($form->getFields() as $field) {
            $html .= $this->field($field);
        }

        $html .= $this->end();

        return $html;
    }

    /**
     * @param array<string, string> $attributes
     */
    public function start(
        Form $form,
        string $action = '',
        string $method = 'POST',
        array $attributes = []
    ): string {
        $attr = $this->attributes(array_merge([
            'action' => $action,
            'method' => strtoupper($method) === 'GET' ? 'GET' : 'POST',
        ], $attributes));

        $html = "<form{$attr}>";

        if ($form->isCsrfProtected()) {
            $token = $this->escape($form->getCsrfToken());
            $name = $this->escape(Form::CSRF_FIELD);
            $html .= "\n    <input type=\"hidden\" name=\"{$name}\" value=\"{$token}\">";
        }

        return $html;
    }

    public function field(FormField $field): string
    {
        if ($field->getName() === Form::CSRF_FIELD) {
            return '';
        }

        if ($field->getType() === FieldType::Hidden) {
            return "\n    " . $this->widget($field);
        }

        $label = $this->label($field);
        $control = $this->widget($field);
        $errors = $this->errors($field);

        return <<<HTML

    <div class="form-group">
        {$label}
        {$control}{$errors}
    </div>
HTML;
    }

    public function label(FormField $field): string
    {
        $required = $field->isRequired() ? ' <span class="required">*</span>' : '';

        return "<label for=\"{$this->escape($field->getName())}\">{$this->escape($field->getLabel())}{$required}</label>";
    }

    public function widget(FormField $field): string
    {
        if ($field->getType() === FieldType::Hidden) {
            return $this->input($field, 'hidden');
        }

        return $this->control($field);
    }

    public function errors(FormField $field): string
    {
        if (!$field->hasErrors()) {
            return '';
        }

        $items = '';
        foreach ($field->getErrors() as $error) {
            $items .= "\n            <li>{$this->escape($error)}</li>";
        }

        return "\n        <ul class=\"form-errors\">{$items}\n        </ul>";
    }

    public function end(): string
    {
        return "\n</form>";
    }

    private function control(FormField $field): string
    {
        return match ($field->getType()) {
            FieldType::Textarea => $this->textarea($field),
            FieldType::Select => $this->select($field),
            FieldType::Checkbox => $this->checkbox($field),
            FieldType::Email => $this->input($field, 'email'),
            FieldType::Password => $this->input($field, 'password'),
            FieldType::Number => $this->input($field, 'number'),
            FieldType::Date => $this->input($field, 'date'),
            FieldType::DateTime => $this->input($field, 'datetime-local'),
            default => $this->input($field, 'text'),
        };
    }

    private function input(FormField $field, string $htmlType): string
    {
        $name = $this->escape($field->getName());
        $value = $this->escape($this->displayValue($field));
        $extra = $this->fieldAttributes($field);

        return "<input type=\"{$htmlType}\" id=\"{$name}\" name=\"{$name}\" value=\"{$value}\"{$extra}>";
    }

    private function textarea(FormField $field): string
    {
        $name = $this->escape($field->getName());
        $value = $this->escape($this->displayValue($field));
        $extra = $this->fieldAttributes($field);

        return "<textarea id=\"{$name}\" name=\"{$name}\"{$extra}>{$value}</textarea>";
    }

    private function checkbox(FormField $field): string
    {
        $name = $this->escape($field->getName());
        $checked = $field->getValue() ? ' checked' : '';
        $extra = $this->fieldAttributes($field);

        return "<input type=\"checkbox\" id=\"{$name}\" name=\"{$name}\" value=\"1\"{$checked}{$extra}>";
    }

    private function select(FormField $field): string
    {
        $name = $this->escape($field->getName());
        $extra = $this->fieldAttributes($field);
        $current = $field->getValue();
        $choices = $field->getChoices();
        $isList = array_is_list($choices);

        $options = '';
        foreach ($choices as $key => $label) {
            $optionValue = $isList ? $label : $key;
            $selected = (string) $optionValue === (string) $current ? ' selected' : '';
            $options .= "\n            <option value=\"{$this->escape((string) $optionValue)}\"{$selected}>{$this->escape((string) $label)}</option>";
        }

        return "<select id=\"{$name}\" name=\"{$name}\"{$extra}>{$options}\n        </select>";
    }

    private function fieldAttributes(FormField $field): string
    {
        $attr = (array) $field->getOption('attr', []);

        if ($field->isRequired()) {
            $attr['required'] = 'required';
        }
        $placeholder = $field->getOption('placeholder');
        if ($placeholder !== null) {
            $attr['placeholder'] = $placeholder;
        }

        return $this->attributes($attr);
    }

    private function displayValue(FormField $field): string
    {
        $value = $field->getValue();

        if ($value instanceof DateTimeInterface) {
            return match ($field->getType()) {
                FieldType::Date => $value->format('Y-m-d'),
                FieldType::DateTime => $value->format('Y-m-d\TH:i'),
                default => $value->format('Y-m-d H:i:s'),
            };
        }

        if (is_bool($value)) {
            return $value ? '1' : '';
        }

        return $value === null ? '' : (string) $value;
    }

    /**
     * @param array<string, mixed> $attributes
     */
    private function attributes(array $attributes): string
    {
        $html = '';
        foreach ($attributes as $key => $value) {
            if ($value === null || $value === '') {
                continue;
            }
            $html .= ' ' . $this->escape((string) $key) . '="' . $this->escape((string) $value) . '"';
        }
        return $html;
    }

    private function escape(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}