<?php
declare(strict_types=1);

namespace Neo\Core\Database\Form\Type;

use Neo\Core\Database\Form\FormField;

class RadioType extends AbstractType
{
    public ?string $type = 'radio';

    public function render(FormField $field): string
    {
        $name = htmlspecialchars($field->getName(), ENT_QUOTES, 'UTF-8');
        $choices = $field->getOption('choices', []);
        $html = '';

        foreach ($choices as $value => $labelText) {
            $id = $this->getId($field);
            $valueSafe = htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
            $labelSafe = htmlspecialchars((string)$labelText, ENT_QUOTES, 'UTF-8');
            $checked = $field->getValue() == $value;

            $attrs = $this->buildAttributes(
                array_merge(
                    ['checked' => $checked],
                    $this->collectAttrs($field, ['choices'])
                )
            );

            $html .= <<<HTML
<input type="radio" name="{$name}" id="{$id}" value="{$valueSafe}"{$attrs} />
<label for="{$id}">{$labelSafe}</label>
HTML;
        }

        return $html;
    }
}
