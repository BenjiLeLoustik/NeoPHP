<?php
declare(strict_types=1);

namespace Neo\Core\Database\Form\Type;

use Neo\Core\Database\Form\FormField;

class HiddenType extends AbstractType
{
    public ?string $type = 'hidden';

    public function render(FormField $field): string
    {
        $value = htmlspecialchars((string)($field->getValue() ?? ''), ENT_QUOTES);
        $name = $field->getName();
        $id = $field->getOption('id', $name);

        $attrs = '';
        foreach ($field->getOptions() as $k => $v) {
            if (!in_array($k, ['label', 'value'])) {
                $attrs .= " {$k}='{$v}'";
            }
        }

        return <<<HTML
<input type='hidden' name='{$name}' id='{$id}' value='{$value}'{$attrs} />
HTML;

    }
}
