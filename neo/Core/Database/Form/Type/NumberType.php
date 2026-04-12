<?php
declare(strict_types=1);

namespace Neo\Core\Database\Form\Type;

use Neo\Core\Database\Form\FormField;

class NumberType extends AbstractType
{
    public ?string $type = 'number';

    public function render(FormField $field): string
    {
        $value = htmlspecialchars((string)($field->getValue() ?? ''), ENT_QUOTES);
        $name  = $field->getName();
        $id    = $field->getOption('id', $name);
        $min   = $field->getOption('min', '');
        $max   = $field->getOption('max', '');
        $step  = $field->getOption('step', '');
        $autocomplete = $field->getOption('autocomplete', 'off');

        $attrs = '';
        foreach ($field->getOptions() as $k => $v) {
            if (!in_array($k, ['label', 'value', 'min', 'max', 'step', 'id'])) {
                $attrs .= " {$k}='{$v}'";
            }
        }

        $minAttr = $min !== '' ? " min='{$min}'" : '';
        $maxAttr = $max !== '' ? " max='{$max}'" : '';
        $stepAttr = $step !== '' ? " step='{$step}'" : '';

        return "<input type='number' name='{$name}' id='{$id}' value='{$value}' autocomplete='{$autocomplete}'{$minAttr}{$maxAttr}{$stepAttr}{$attrs} />";
    }

    public function normalize(mixed $value, ?FormField $field = null): int|float|null
    {
        if ($value === null || $value === '') {
            return null;
        }

        $options = $field?->getOptions() ?? [];
        $cast = $options['cast'] ?? null;

        return match ($cast) {
            'int'   => (int) $value,
            'float' => (float) $value,
            default => is_numeric($value) ? $value + 0 : $value,
        };
    }
}
