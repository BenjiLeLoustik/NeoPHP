<?php
declare(strict_types=1);

namespace Neo\Core\Database\Form\Type;

use Neo\Core\Database\Form\FormField;
use Neo\Core\Database\ORM\Model\AbstractModel;
use Neo\Core\Validator\Validator;

class CollectionType extends AbstractType
{
    public ?string $type = 'collection';

    private static function escape(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
    }

    public function render(FormField $field): string
    {
        $name      = $field->getName();
        $entries   = $this->getEntries($field);
        $prototype = $this->getPrototype($field);
        $allowAdd  = $field->getOption('allow_add', true);
        $addLabel  = $field->getOption('add_label', '+ Ajouter');

        $html = sprintf(
            '<div class="collection-wrapper" data-collection="%s" data-prototype="%s" data-entries="%s" data-index="%d">',
            self::escape($name),
            self::escape($prototype),
            self::escape(json_encode($entries)),
            count($entries)
        );

        $html .= '<div class="collection-entries"></div>';

        $html .= sprintf(
            '<button type="button" class="collection-add" data-target="%s">%s</button>',
            self::escape($name),
            $addLabel
        );


        $html .= '</div>';
        return $html;
    }

    public function renderEntry(
        string $collectionName,
        string $index,
        array  $entryFields,
        bool   $allowDelete,
        string $deleteLabel,
        array  $values = [],
        array  $collectionErrors = []
    ): string {
        $html = sprintf(
            '<div class="collection-entry" data-entry-index="%s">',
            self::escape($index)
        );

        foreach ($entryFields as $fieldName => $fieldConfig) {
            [$typeClass, $options] = $this->resolveFieldConfig($fieldConfig);

            $inputName    = sprintf('%s[%s][%s]', $collectionName, $index, $fieldName);
            $inputId      = sprintf('%s_%s_%s', $collectionName, $index, $fieldName);
            $inputType    = $this->resolveHtmlInputType($typeClass);
            $value        = self::escape((string)($values[$fieldName] ?? $options['value'] ?? ''));
            $labelText    = self::escape($options['label'] ?? ucfirst($fieldName));
            $extraAttrs   = $this->buildExtraAttrs($options);

            $errorKey     = sprintf('%s[%s][%s]', $collectionName, $index, $fieldName);
            $fieldErrors  = $collectionErrors[$errorKey] ?? [];
            $controlClass = 'form-control' . (!empty($fieldErrors) ? ' form-control--error' : '');

            $html .= '<div class="form-field">';
            $html .= sprintf('<label for="%s">%s</label>', $inputId, $labelText);
            $html .= sprintf('<section class="%s">', $controlClass);
            $html .= '<div class="form-control-inner">';
            $html .= '<div class="form-input-wrapper">';
            $html .= sprintf(
                '<input type="%s" name="%s" id="%s" value="%s"%s />',
                $inputType, $inputName, $inputId, $value, $extraAttrs
            );
            $html .= '</div>';  // form-input-wrapper
            $html .= '</div>';  // form-control-inner
            $html .= '</section>'; // form-control

            foreach ($fieldErrors as $errorMessage) {
                $html .= sprintf(
                    '<span class="form-error">%s</span>',
                    self::escape($errorMessage)
                );
            }

            $html .= '</div>'; // form-field
        }

        $html .= sprintf(
            '<button type="button" class="collection-remove" aria-label="%s">%s</button>',
            strip_tags($deleteLabel), // aria-label sans HTML
            $deleteLabel              // contenu brut
        );

        $html .= '</div>'; // collection-entry
        return $html;
    }

    public function getPrototype(FormField $field): string
    {
        return $this->renderEntry(
            $field->getName(),
            '__name__',
            $field->getOption('entry_fields', []),
            $field->getOption('allow_delete', true),
            $field->getOption('delete_label', 'Supprimer'),
        );
    }

    public function getEntries(FormField $field): array
    {
        $entries    = $field->getValue() ?? [];
        $normalized = [];

        foreach ($entries as $entry) {
            if ($entry instanceof AbstractModel) {
                $normalized[] = $entry->toArray(includeRelations: false);
            } elseif (is_object($entry)) {
                $normalized[] = get_object_vars($entry);
            } elseif (is_array($entry)) {
                $normalized[] = $entry;
            }
        }

        return $normalized;
    }

    public function getEntryFields(FormField $field): array
    {
        return $field->getOption('entry_fields', []);
    }

    public function getAllowAdd(FormField $field): bool
    {
        return $field->getOption('allow_add', true);
    }

    public function getAllowDelete(FormField $field): bool
    {
        return $field->getOption('allow_delete', true);
    }

    public function normalize(mixed $value, ?FormField $field = null): array
    {
        if (!is_array($value)) {
            return [];
        }

        $modelClass = $field?->getOption('entry_model');
        $result     = [];

        foreach ($value as $entry) {
            if (!is_array($entry)) {
                continue;
            }

            // On garde l'entrée même si certains champs sont vides,
            // la validation s'en chargera
            if ($modelClass !== null && class_exists($modelClass)) {
                $result[] = new $modelClass($entry);
            } else {
                $result[] = $entry;
            }
        }

        return array_values($result);
    }

    public function validateEntries(FormField $field, Validator $validator): array
    {
        $entries    = $field->getValue() ?? [];
        $name       = $field->getName();
        $modelClass = $field->getOption('entry_model');
        $errors     = [];

        if ($modelClass === null || !class_exists($modelClass)) {
            return [];
        }

        foreach ($entries as $index => $entry) {
            $model = $entry instanceof $modelClass
                ? $entry
                : new $modelClass(is_array($entry) ? $entry : []);

            $entryErrors = $validator->validate($model);

            foreach ($entryErrors as $fieldName => $messages) {
                $key          = sprintf('%s[%d][%s]', $name, $index, $fieldName);
                $errors[$key] = $messages;
            }
        }

        return $errors;
    }

    private function resolveFieldConfig(mixed $config): array
    {
        if (is_string($config)) {
            return [$config, []];
        }
        if (is_array($config)) {
            return [$config[0] ?? TextType::class, $config[1] ?? []];
        }
        return [TextType::class, []];
    }

    private function resolveHtmlInputType(string $typeClass): string
    {
        return match (true) {
            str_ends_with($typeClass, 'NumberType') => 'number',
            str_ends_with($typeClass, 'EmailType')  => 'email',
            str_ends_with($typeClass, 'HiddenType') => 'hidden',
            str_ends_with($typeClass, 'DateType')   => 'date',
            default                                 => 'text',
        };
    }

    private function buildExtraAttrs(array $options): string
    {
        $ignored = ['label', 'value', 'type'];
        $booleanAttrs = ['required', 'disabled', 'readonly', 'checked', 'multiple'];
        $attrs = '';

        foreach ($options as $k => $v) {
            if (in_array($k, $ignored, true)) {
                continue;
            }
            if (in_array($k, $booleanAttrs, true)) {
                if ($v) {
                    $attrs .= sprintf(' %s', $k); // <input required />
                }
                continue;
            }
            $attrs .= sprintf(' %s="%s"', $k, self::escape((string)$v));
        }

        return $attrs;
    }
}