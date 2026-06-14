<?php
declare(strict_types=1);

namespace Neo\Core\Database\Form;

use Neo\Core\Database\Form\Type\CollectionType;
use Neo\Core\Database\Form\Type\SubmitType;

class FormExtension
{
    private static function escape(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
    }

    /**
     * @param array<string, string|int|float|bool> $attrs
     */
    private static function buildAttributes(array $attrs): string
    {
        $html = '';
        foreach ($attrs as $k => $v) {
            $html .= ' ' . $k . '="' . self::escape((string) $v) . '"';
        }
        return $html;
    }

    /**
     * @param array{action?: string, method?: string, attr?: array<string, string|int|float|bool>} $params
     */
    public static function formStart(Form $form, array $params = []): string
    {
        $action = self::escape((string) ($params['action'] ?? ''));
        $method = self::escape((string) ($params['method'] ?? 'POST'));
        /** @phpstan-ignore-next-line */
        $attrs = !empty($params['attr']) && is_array($params['attr'])
            ? self::buildAttributes($params['attr'])
            : '';

        return "<form action=\"{$action}\" method=\"{$method}\"{$attrs}>";
    }

    public static function formCsrf(Form $form): string
    {
        $field = $form->getField('_csrf');
        if (!$field) {
            return '';
        }

        return sprintf(
            '<input type="hidden" name="%s" value="%s">',
            self::escape($field->getName()),
            self::escape($field->getValue())
        );
    }

    /**
     * @param array<string, mixed> $options
     */
    public static function formWidget(Form $form, string $fieldName, array $options = []): string
    {
        $field = $form->getField($fieldName);
        if (!$field) {
            return '';
        }

        return $field->withOptions($options)->render();
    }

    public static function formField(Form $form, string $fieldName): mixed
    {
        return $form->getField($fieldName);
    }

    public static function formRow(Form $form, string $fieldName): string
    {
        $field = $form->getField($fieldName);
        if (!$field || $field->getName() === '_csrf') {
            return '';
        }

        $wrapperAttrs = $field->getWrapperAttributes();
        $classes = 'form-group';

        if (!empty($wrapperAttrs['class'])) {
            $classes .= ' ' . $wrapperAttrs['class'];
            unset($wrapperAttrs['class']);
        }

        $attrString = ' class="' . self::escape($classes) . '"';
        $attrString .= self::buildAttributes($wrapperAttrs);

        $html = "<div{$attrString}>";
        $html .= $field->render();

        foreach ($field->getErrors() as $err) {
            $html .= '<span class="error">' . self::escape($err) . '</span>';
        }

        $html .= '</div>';
        return $html;
    }

    public static function formLabel(Form $form, string $fieldName): string
    {
        $field = $form->getField($fieldName);
        if (!$field) {
            return '';
        }

        return sprintf(
            '<label for="%s">%s</label>',
            self::escape($field->getName()),
            self::escape($field->getLabel())
        );
    }

    /**
     * @return array<int, string>
     */
    public static function formErrors(Form $form, string $fieldName): array
    {
        $field = $form->getField($fieldName);
        return $field ? $field->getErrors() : [];
    }

    /**
     * @return array<int, string>
     */
    public static function globalErrors(Form $form): array
    {
        return $form->getErrors();
    }

    /**
     * @return array<int, mixed>
     */
    public static function collectionEntries(Form $form, string $fieldName): array
    {
        $field = $form->getField($fieldName);
        if (!$field || !($field->getType() instanceof CollectionType)) {
            return [];
        }

        return $field->getEntries();
    }

    public static function collectionAllowAdd(Form $form, string $fieldName): bool
    {
        $field = $form->getField($fieldName);
        if (!$field || !($field->getType() instanceof CollectionType)) {
            return false;
        }

        return $field->getAllowAdd();
    }

    public static function collectionAllowDelete(Form $form, string $fieldName): bool
    {
        $field = $form->getField($fieldName);
        if (!$field || !($field->getType() instanceof CollectionType)) {
            return false;
        }

        return $field->getAllowDelete();
    }

    public static function collectionIndex(Form $form, string $fieldName): int
    {
        $field = $form->getField($fieldName);
        if (!$field || !($field->getType() instanceof CollectionType)) {
            return 0;
        }

        return count($field->getEntries());
    }

    public static function collectionWidget(Form $form, string $fieldName): string
    {
        $field = $form->getField($fieldName);
        if (!$field || !($field->getType() instanceof CollectionType)) {
            return '';
        }

        $type = $field->getType();
        $entries = $type->getEntries($field);
        $prototype = $type->getPrototype($field);

        $html = sprintf(
            '<div class="collection-wrapper" data-collection="%s" data-prototype="%s" data-index="%d">',
            self::escape($fieldName),
            self::escape($prototype),
            count($entries)
        );

        $html .= '<div class="collection-entries">';

        foreach ($entries as $index => $entry) {
            $html .= $type->renderEntry(
                $fieldName,
                (string) $index,
                $type->getEntryFields($field),
                $type->getAllowDelete($field),
                $field->getOption('delete_label', 'Supprimer'),
                $entry,
                $field->getCollectionErrors()
            );
        }

        $html .= '</div>';

        if ($type->getAllowAdd($field)) {
            $addLabel = $field->getOption('add_label', '+ Ajouter');
            $html .= sprintf(
                '<button type="button" class="collection-add" data-target="%s">%s</button>',
                self::escape($fieldName),
                $addLabel
            );
        }

        $html .= '</div>';
        return $html;
    }

    public static function collectionPrototype(Form $form, string $fieldName): string
    {
        $field = $form->getField($fieldName);
        if (!$field || !($field->getType() instanceof CollectionType)) {
            return '';
        }

        return $field->getType()->getPrototype($field);
    }

    public static function collectionLabel(Form $form, string $fieldName, string $entryFieldName): string
    {
        $field = $form->getField($fieldName);
        if (!$field || !($field->getType() instanceof CollectionType)) {
            return '';
        }

        $entryFields = $field->getEntryFields();
        $fieldConfig = $entryFields[$entryFieldName] ?? null;
        if (!$fieldConfig) {
            return '';
        }

        [, $options] = is_array($fieldConfig)
            ? [$fieldConfig[0], $fieldConfig[1] ?? []]
            : [$fieldConfig, []];

        $inputId = sprintf('%s_%s_%s', $fieldName, '__name__', $entryFieldName);
        $labelText = self::escape($options['label'] ?? ucfirst($entryFieldName));

        return sprintf('<label for="%s">%s</label>', $inputId, $labelText);
    }

    /**
     * @return array<int, string>
     */
    public static function collectionError(Form $form, string $fieldName, string|int $index, string $entryFieldName): array
    {
        $field = $form->getField($fieldName);
        if (!$field || !($field->getType() instanceof CollectionType)) {
            return [];
        }

        $errorKey = sprintf('%s[%s][%s]', $fieldName, $index, $entryFieldName);
        return $field->getCollectionErrors()[$errorKey] ?? [];
    }

    /**
     * @param array{action?: string, method?: string, attr?: array<string, string|int|float|bool>} $params
     */
    public static function fullForm(Form $form, array $params = []): string
    {
        $action = self::escape((string) ($params['action'] ?? ''));
        $method = self::escape((string) ($params['method'] ?? 'POST'));
        /** @phpstan-ignore-next-line */
        $attrs = !empty($params['attr']) && is_array($params['attr'])
            ? self::buildAttributes($params['attr'])
            : '';

        $html = "<form action=\"{$action}\" method=\"{$method}\"{$attrs}>";

        foreach ($form->getFields() as $field) {
            if ($field->getName() === '_csrf') {
                continue;
            }

            if ($field->getType() instanceof SubmitType) {
                $html .= $field->render();
                continue;
            }

            $wrapperAttrs = $field->getWrapperAttributes();
            $classes = 'form-group';

            if (!empty($wrapperAttrs['class'])) {
                $classes .= ' ' . $wrapperAttrs['class'];
                unset($wrapperAttrs['class']);
            }

            $attrString = ' class="' . self::escape($classes) . '"';
            $attrString .= self::buildAttributes($wrapperAttrs);

            $html .= "<div{$attrString}>";

            if ($label = $field->getLabel()) {
                $html .= sprintf(
                    '<label for="%s">%s</label>',
                    self::escape($field->getId()),
                    self::escape($label)
                );
            }

            $html .= $field->render();

            foreach ($field->getErrors() as $err) {
                $html .= '<span class="error">' . self::escape($err) . '</span>';
            }

            $html .= '</div>';
        }

        if ($csrf = $form->getField('_csrf')) {
            $html .= sprintf(
                '<input type="hidden" name="%s" value="%s">',
                self::escape($csrf->getName()),
                self::escape($csrf->getValue())
            );
        }

        $html .= '</form>';
        return $html;
    }
}