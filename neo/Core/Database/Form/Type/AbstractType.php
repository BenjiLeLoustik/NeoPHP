<?php
declare(strict_types=1);

namespace Neo\Core\Database\Form\Type;

use Neo\Core\Database\Form\FormField;

abstract class AbstractType
{
    public ?string $type = null;

    abstract public function render(FormField $field): string;

    public function normalize(mixed $value, ?FormField $field = null): mixed
    {
        return $value;
    }

    protected function getDefaultIgnored(): array
    {
        return ['label', 'value', 'id', 'autocomplete'];
    }

    protected function collectAttrs(FormField $field, array $extraIgnored = []): array
    {
        $ignored = array_merge($this->getDefaultIgnored(), $extraIgnored);
        $attrs = [];

        foreach ($field->getOptions() as $k => $v) {
            if (!in_array($k, $ignored, true)) {
                $attrs[$k] = $v;
            }
        }

        return $attrs;
    }

    protected function buildAttributes(array $attrs): string
    {
        $booleanAttrs = ['required', 'disabled', 'readonly', 'checked', 'multiple', 'autofocus'];
        $html = '';

        foreach ($attrs as $k => $v) {
            $k = htmlentities((string)$k, ENT_QUOTES, 'UTF-8');

            if (in_array($k, $booleanAttrs, true)) {
                if ($v) {
                    $html .= " {$k}";
                }
                continue;
            }

            if ($v === true) {
                $html .= " {$k}";
            } elseif ($v !== false && $v !== null) {
                $escaped = htmlentities((string)$v, ENT_QUOTES, 'UTF-8');
                $html .= " {$k}=\"{$escaped}\"";
            }
        }

        return $html;
    }
}