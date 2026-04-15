<?php
declare(strict_types=1);

namespace Neo\Core\Database\Form;

use Neo\Core\Database\Form\Type\CollectionType;
use Neo\Core\Database\Form\Type\SubmitType;
use Neo\Core\DI\Container;
use Neo\Core\Translation\TranslationManager;
use Neo\Core\View\View;

class FormExtension
{
    private static function escape(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
    }

    private static function buildAttributes(array $attrs): string
    {
        $html = '';
        foreach ($attrs as $k => $v) {
            $html .= ' ' . $k . '="' . self::escape((string) $v) . '"';
        }
        return $html;
    }

    public static function register(Container $container): void
    {
        $view = $container->get(View::class);
        $translator = $container->get(TranslationManager::class);

        /* ---------- FORM START ---------- */
        $view->registerTwigFunction('form_start', function (Form $form, array $params = []) {
            $action = self::escape((string) ($params['action'] ?? ''));
            $method = self::escape((string) ($params['method'] ?? 'POST'));
            $attrs  = !empty($params['attr']) && is_array($params['attr'])
                ? self::buildAttributes($params['attr'])
                : '';

            return "<form action=\"{$action}\" method=\"{$method}\"{$attrs}>";
        }, ['is_safe' => ['html']]);

        /* ---------- CSRF ---------- */
        $view->registerTwigFunction('form_csrf', function (Form $form) {
            $field = $form->getField('_csrf');
            if (!$field) {
                return '';
            }

            return sprintf(
                '<input type="hidden" name="%s" value="%s">',
                self::escape($field->getName()),
                self::escape($field->getValue())
            );
        }, ['is_safe' => ['html']]);

        /* ---------- FORM END ---------- */
        $view->registerTwigFunction('form_end', fn () => '</form>', ['is_safe' => ['html']]);

        /* ---------- WIDGET ---------- */
        $view->registerTwigFunction('form_widget', function (Form $form, string $fieldName, array $options = []) {
            $field = $form->getField($fieldName);

            if (!$field) return '';

            if (isset($options['attr']) && is_array($options['attr'])) {
                $field->setOption('attrs', array_merge(
                    $field->getAttributes(),
                    $options['attr']
                ));
            }

            return $field->render();
        }, ['is_safe' => ['html']]);

        /* ---------- FIELD ---------- */
        $view->registerTwigFunction('form_field', function (Form $form, string $fieldName) {
            return $form->getField($fieldName);
        });

        /* ---------- ROW ---------- */
        $view->registerTwigFunction('form_row', function (Form $form, string $fieldName) {
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

            $html  = "<div{$attrString}>";
            $html .= $field->render();

            foreach ($field->getErrors() as $err) {
                $html .= '<span class="error">' . self::escape($err) . '</span>';
            }

            $html .= '</div>';
            return $html;
        }, ['is_safe' => ['html']]);

        /* ---------- LABEL ---------- */
        $view->registerTwigFunction('form_label', function (Form $form, string $fieldName) {
            $field = $form->getField($fieldName);
            if (!$field) {
                return '';
            }

            return sprintf(
                '<label for="%s">%s</label>',
                self::escape($field->getName()),
                self::escape($field->getLabel())
            );
        }, ['is_safe' => ['html']]);

        /* ---------- FIELD ERRORS (ARRAY) ---------- */
        $view->registerTwigFunction('form_error', function (Form $form, string $fieldName) use ($translator) {
            $field = $form->getField($fieldName);
            if (!$field) {
                return [];
            }

            return array_map(
                fn ($err) => $translator->translate($err),
                $field->getErrors()
            );
        });

        /* ---------- FORM ERRORS (ARRAY) ---------- */
        $view->registerTwigFunction('form_errors', function (Form $form) use ($translator) {
            return array_map(
                fn ($err) => $translator->translate($err),
                $form->getErrors()
            );
        });

        /* ---------- COLLECTION ENTRIES ---------- */
        $view->registerTwigFunction('collection_entries', function (Form $form, string $fieldName) {
            $field = $form->getField($fieldName);
            if (!$field || !($field->getType() instanceof CollectionType)) {
                return [];
            }

            return $field->getEntries();
        });

        /* ---------- COLLECTION ALLOW ADD ---------- */
        $view->registerTwigFunction('collection_allow_add', function (Form $form, string $fieldName) {
            $field = $form->getField($fieldName);
            if (!$field || !($field->getType() instanceof CollectionType)) {
                return false;
            }

            return $field->getAllowAdd();
        });

        /* ---------- COLLECTION ALLOW DELETE ---------- */
        $view->registerTwigFunction('collection_allow_delete', function (Form $form, string $fieldName) {
            $field = $form->getField($fieldName);
            if (!$field || !($field->getType() instanceof CollectionType)) {
                return false;
            }

            return $field->getAllowDelete();
        });

        /* ---------- COLLECTION INDEX ---------- */
        $view->registerTwigFunction('collection_index', function (Form $form, string $fieldName) {
            $field = $form->getField($fieldName);
            if (!$field || !($field->getType() instanceof CollectionType)) {
                return 0;
            }

            return count($field->getEntries());
        });

        /* ---------- COLLECTION WIDGET ---------- */
        $view->registerTwigFunction('collection_widget', function (Form $form, string $fieldName) {
            $field = $form->getField($fieldName);
            if (!$field || !($field->getType() instanceof CollectionType)) {
                return '';
            }

            $type     = $field->getType();
            $entries  = $type->getEntries($field);
            $prototype = $type->getPrototype($field);

            $html  = sprintf(
                '<div class="collection-wrapper" data-collection="%s" data-prototype="%s" data-index="%d">',
                self::escape($fieldName),
                self::escape($prototype),
                count($entries)
            );

            $html .= '<div class="collection-entries">';

            foreach ($entries as $index => $entry) {
                $html .= $type->renderEntry(
                    $fieldName,
                    (string)$index,
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
        }, ['is_safe' => ['html']]);

        /* ---------- COLLECTION PROTOTYPE ---------- */
        $view->registerTwigFunction('collection_prototype', function (Form $form, string $fieldName) {
            $field = $form->getField($fieldName);
            if (!$field || !($field->getType() instanceof CollectionType)) {
                return '';
            }

            return $field->getType()->getPrototype($field);
        }, ['is_safe' => ['html']]);

        /* ---------- COLLECTION LABEL ---------- */
        $view->registerTwigFunction('collection_label', function (Form $form, string $fieldName, string $entryFieldName) {
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

            $inputId   = sprintf('%s_%s_%s', $fieldName, '__name__', $entryFieldName);
            $labelText = self::escape($options['label'] ?? ucfirst($entryFieldName));

            return sprintf('<label for="%s">%s</label>', $inputId, $labelText);
        }, ['is_safe' => ['html']]);

        /* ---------- COLLECTION ERRORS ---------- */
        $view->registerTwigFunction('collection_error', function (Form $form, string $fieldName, string|int $index, string $entryFieldName) use ($translator) {
            $field = $form->getField($fieldName);
            if (!$field || !($field->getType() instanceof CollectionType)) {
                return [];
            }

            $errorKey = sprintf('%s[%s][%s]', $fieldName, $index, $entryFieldName);
            $errors   = $field->getCollectionErrors()[$errorKey] ?? [];

            return array_map(
                fn ($err) => $translator->translate($err),
                $errors
            );
        });

        /* ---------- FULL FORM ---------- */
        $view->registerTwigFunction('form', function (Form $form, array $params = []) {
            $action = self::escape((string) ($params['action'] ?? ''));
            $method = self::escape((string) ($params['method'] ?? 'POST'));
            $attrs  = !empty($params['attr']) && is_array($params['attr'])
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
        }, ['is_safe' => ['html']]);
    }
}