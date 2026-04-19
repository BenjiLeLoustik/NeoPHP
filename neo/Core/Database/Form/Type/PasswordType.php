<?php
declare(strict_types=1);

namespace Neo\Core\Database\Form\Type;

use Neo\Core\Database\Form\FormField;

class PasswordType extends AbstractType
{
    public ?string $type = 'password';

    public function render(FormField $field): string
    {
        $name = htmlspecialchars($field->getName(), ENT_QUOTES, 'UTF-8');
        $id = $this->getId($field);
        $value = htmlspecialchars((string)($field->getValue() ?? ''), ENT_QUOTES, 'UTF-8');
        $autocomplete = htmlspecialchars($field->getOption('autocomplete', 'current-password'), ENT_QUOTES, 'UTF-8');

        $attrs = $this->buildAttributes($this->collectAttrs($field));

        return <<<HTML
<input type="password" name="{$name}" id="{$id}" value="{$value}" autocomplete="{$autocomplete}"{$attrs} />
HTML;
    }
}
