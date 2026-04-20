<?php
declare(strict_types=1);

namespace Neo\Core\Database\Form\Type;

use Neo\Core\Database\Form\FormField;

class HiddenType extends AbstractType
{
    public ?string $type = 'hidden';

    public function render(FormField $field): string
    {
        $name = htmlspecialchars($field->getName(), ENT_QUOTES, 'UTF-8');
        $id = $this->getId($field);
        $value = htmlspecialchars((string)($field->getValue() ?? ''), ENT_QUOTES, 'UTF-8');
        $attrs = $this->buildAttributes($this->collectAttrs($field));

        return <<<HTML
<input type="hidden" name="{$name}" id="{$id}" value="{$value}"{$attrs} />
HTML;
    }
}
