<?php
declare(strict_types=1);

namespace Neo\Core\Database\Form\Type;

use Neo\Core\Database\Form\FormField;

class DateType extends AbstractType
{
    public ?string $type = 'date';

    public function render(FormField $field): string
    {
        $rawValue = $field->getValue();
        $value = '';

        if ($rawValue instanceof \DateTimeInterface) {
            $value = $rawValue->format('Y-m-d');
        } elseif (is_string($rawValue) && $rawValue !== '') {
            if (preg_match('/^\d{4}-\d{2}-\d{2}/', $rawValue)) {
                $value = substr($rawValue, 0, 10);
            }
        }

        $value = htmlspecialchars($value, ENT_QUOTES);

        $name  = $field->getName();
        $id    = $field->getOption('id', $name);

        $autocomplete = $field->getOption('autocomplete', 'off');
        $min = $field->getOption('min', '');
        $max = $field->getOption('max', '');
        $placeholder = $field->getOption('placeholder', '');

        $attrs = '';
        $excluded = ['label', 'value', 'min', 'max', 'placeholder', 'autocomplete', 'id'];

        foreach ($field->getOptions() as $k => $v) {
            if (!in_array($k, $excluded, true)) {
                $safe = htmlspecialchars((string)$v, ENT_QUOTES);
                $attrs .= " {$k}=\"{$safe}\"";
            }
        }

        $minAttr = $min ? "min=\"{$min}\"" : "";
        $maxAttr = $max ? "max=\"{$max}\"" : "";
        $placeholderAttr = $placeholder ? "placeholder=\"{$placeholder}\"" : "";

        return <<<HTML
<input type="date"" name="{$name}" id="{$id}" value="{$value}" autocomplete="{$autocomplete}" {$minAttr} {$maxAttr} {$placeholderAttr} {$attrs} />
HTML;
    }

    public function normalize(mixed $value, ?FormField $field = null): mixed
    {
        if (!$value) {
            return null;
        }

        try {
            return new \DateTime($value);
        } catch (\Throwable) {
            return null;
        }
    }
}
