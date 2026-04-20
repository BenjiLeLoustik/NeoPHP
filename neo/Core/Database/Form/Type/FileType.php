<?php
declare(strict_types=1);

namespace Neo\Core\Database\Form\Type;

use Neo\Core\Database\Form\FormField;

class FileType extends AbstractType
{
    public ?string $type = 'file';

    protected function getDefaultIgnored(): array
    {
        return ['label', 'value', 'id'];
    }

    public function render(FormField $field): string
    {
        $name = htmlspecialchars($field->getName(), ENT_QUOTES, 'UTF-8');
        $id = $this->getId($field);
        $attrs = $this->buildAttributes($this->collectAttrs($field));

        return <<<HTML
<input type="file" name="{$name}" id="{$id}"{$attrs} />
HTML;
    }
}
