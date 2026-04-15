<?php
declare(strict_types=1);

namespace Neo\Core\Database\Form\Type;

use Neo\Core\Database\Form\FormField;

class NumberType extends AbstractType
{
    public ?string $type = 'number';

    public function render(FormField $field): string
    {
        $name = htmlspecialchars($field->getName(), ENT_QUOTES, 'UTF-8');
        $id = htmlspecialchars($field->getOption('id', $field->getName()), ENT_QUOTES, 'UTF-8');
        $value = htmlspecialchars((string)($field->getValue() ?? ''), ENT_QUOTES, 'UTF-8');
        $autocomplete = htmlspecialchars($field->getOption('autocomplete', 'off'), ENT_QUOTES, 'UTF-8');

        $explicit = [];
        foreach (['min', 'max', 'step'] as $key) {
            $v = $field->getOption($key, '');
            if ($v !== '') {
                $explicit[$key] = $v;
            }
        }

        $attrs = $this->buildAttributes(
            array_merge(
                $explicit,
                $this->collectAttrs($field, ['min', 'max', 'step', 'cast'])
            )
        );

        return <<<HTML
<input type="number" name="{$name}" id="{$id}" value="{$value}" autocomplete="{$autocomplete}"{$attrs} />
HTML;
    }

    public function normalize(mixed $value, ?FormField $field = null): int|float|null
    {
        if ($value === null || $value === '') {
            return null;
        }

        $cast = $field?->getOption('cast');

        return match ($cast) {
            'int'   => (int) $value,
            'float' => (float) $value,
            default => is_numeric($value) ? $value + 0 : $value,
        };
    }
}
