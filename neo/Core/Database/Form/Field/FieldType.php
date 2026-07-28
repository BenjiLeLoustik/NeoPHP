<?php
declare(strict_types=1);

namespace Neo\Core\Database\Form\Field;

enum FieldType: string
{
    case Text = 'text';
    case Textarea = 'textarea';
    case Email = 'email';
    case Password = 'password';
    case Number = 'number';
    case Hidden = 'hidden';
    case Checkbox = 'checkbox';
    case Select = 'select';
    case Date = 'date';
    case DateTime = 'datetime-local';

    public static function fromName(string $name): self
    {
        return self::tryFrom($name) ?? self::Text;
    }
}