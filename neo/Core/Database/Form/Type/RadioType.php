<?php
declare(strict_types=1);

namespace Neo\Core\Database\Form\Type;

use Neo\Core\Database\Form\FormField;

class RadioType extends AbstractType
{
    public ?string $type = 'radio';

    public function render(FormField $field): string
    {
        $name = $field->getName();
        $options = $field->getOption('choices', []);
        $html = '';

        foreach ($options as $value => $labelText) {
            $id = $name . '_' . $value;
            $checked = $field->getValue() == $value ? 'checked' : '';

            $html .= <<<HTML
<input type="radio" name="{$name}" id="{$id}" value="{$value}" {$checked} />
<label for="{$id}">{$labelText}</label>
HTML;
        }

        return $html;
    }
}
