<?php
declare(strict_types=1);

namespace Neo\Core\Database\Form\Type;

use Neo\Core\Database\Form\FormField;

class FileType extends AbstractType
{
    public ?string $type = 'file';

    public function render(FormField $field): string
    {
        $name = $field->getName();
        $id = $field->getOption('id', $name);

        $attrs = '';
        foreach ($field->getOptions() as $k => $v) {
            if (!in_array($k, ['label', 'value'])) {
                $attrs .= " {$k}='{$v}'";
            }
        }

        return <<<HTML
<input type='file' name='{$name}' id='{$id}'{$attrs} />
HTML;
    }
}
