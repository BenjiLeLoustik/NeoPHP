<?php
declare(strict_types=1);

namespace Neo\Core\Database\Form\Type;

use Neo\Core\Database\Form\FormField;

class CheckboxType extends AbstractType
{
    public ?string $type = 'checkbox';

    public function render(FormField $field): string
    {
        $name = htmlspecialchars($field->getName(), ENT_QUOTES, 'UTF-8');
        $id = $this->getId($field);
        $checked = (bool)$field->getValue();

        $attrs = $this->buildAttributes(array_merge(
            ['checked' => $checked],
            $this->collectAttrs($field)
        ));

        return <<<HTML
<input type="hidden" name="{$name}" value="0" />
<input type="checkbox" name="{$name}" id="{$id}" value="1"{$attrs} />
HTML;
    }

    public function normalize(mixed $value, ?FormField $field = null): mixed
    {
        return $value ? 1 : 0;
    }
}
