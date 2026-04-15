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


        $name = htmlspecialchars($field->getName(), ENT_QUOTES, 'UTF-8');
        $id = htmlspecialchars($field->getOption('id', $field->getName()), ENT_QUOTES, 'UTF-8');
        $value = htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
        $autocomplete = htmlspecialchars($field->getOption('autocomplete', 'off'), ENT_QUOTES, 'UTF-8');

        $explicit = [];
        foreach (['min', 'max', 'placeholder'] as $key) {
            $v = $field->getOption($key, '');
            if ($v !== '') {
                $explicit[$key] = $v;
            }
        }

        $attrs = $this->buildAttributes(
            array_merge(
                $explicit,
                $this->collectAttrs($field, ['min', 'max', 'placeholder'])
            )
        );

        return <<<HTML
<input type="date" name="{$name}" id="{$id}" value="{$value}" autocomplete="{$autocomplete}"{$attrs} />
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
