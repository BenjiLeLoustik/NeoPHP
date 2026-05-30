<?php
declare(strict_types=1);

namespace Neo\Core\Database\Form;

use Neo\Core\Translation\TranslationManager;
use Neo\Core\View\Interface\TwigExtensionInterface;

final class FormViewExtension implements TwigExtensionInterface
{
    public function __construct(
        private readonly TranslationManager $translator
    ) {}

    public function getFunctions(): array
    {
        return [
            'form_start' => [
                'callable' => fn(Form $form, array $params = []) => FormExtension::formStart($form, $params),
                'options' => ['is_safe' => ['html']],
            ],
            'form_csrf' => [
                'callable' => fn(Form $form) => FormExtension::formCsrf($form),
                'options' => ['is_safe' => ['html']],
            ],
            'form_end' => [
                'callable' => fn() => '</form>',
                'options' => ['is_safe' => ['html']],
            ],
            'form_widget' => [
                'callable' => fn(Form $form, string $fieldName, array $options = []) => FormExtension::formWidget($form, $fieldName, $options),
                'options' => ['is_safe' => ['html']],
            ],
            'form_field' => [
                'callable' => fn(Form $form, string $fieldName) => FormExtension::formField($form, $fieldName),
                'options' => [],
            ],
            'form_row' => [
                'callable' => fn(Form $form, string $fieldName) => FormExtension::formRow($form, $fieldName),
                'options' => ['is_safe' => ['html']],
            ],
            'form_label' => [
                'callable' => fn(Form $form, string $fieldName) => FormExtension::formLabel($form, $fieldName),
                'options' => ['is_safe' => ['html']],
            ],
            'form_error' => [
                'callable' => fn(Form $form, string $fieldName) => array_map(
                    fn($err) => $this->translator->translate($err),
                    FormExtension::formErrors($form, $fieldName)
                ),
                'options' => [],
            ],
            'form_errors' => [
                'callable' => fn(Form $form) => array_map(
                    fn($err) => $this->translator->translate($err),
                    FormExtension::globalErrors($form)
                ),
                'options' => [],
            ],
            'collection_entries' => [
                'callable' => fn(Form $form, string $fieldName) => FormExtension::collectionEntries($form, $fieldName),
                'options' => [],
            ],
            'collection_allow_add' => [
                'callable' => fn(Form $form, string $fieldName) => FormExtension::collectionAllowAdd($form, $fieldName),
                'options' => [],
            ],
            'collection_allow_delete' => [
                'callable' => fn(Form $form, string $fieldName) => FormExtension::collectionAllowDelete($form, $fieldName),
                'options' => [],
            ],
            'collection_index' => [
                'callable' => fn(Form $form, string $fieldName) => FormExtension::collectionIndex($form, $fieldName),
                'options' => [],
            ],
            'collection_widget' => [
                'callable' => fn(Form $form, string $fieldName) => FormExtension::collectionWidget($form, $fieldName),
                'options' => ['is_safe' => ['html']],
            ],
            'collection_prototype' => [
                'callable' => fn(Form $form, string $fieldName) => FormExtension::collectionPrototype($form, $fieldName),
                'options' => ['is_safe' => ['html']],
            ],
            'collection_label' => [
                'callable' => fn(Form $form, string $fieldName, string $entryFieldName) => FormExtension::collectionLabel($form, $fieldName, $entryFieldName),
                'options' => ['is_safe' => ['html']],
            ],
            'collection_error' => [
                'callable' => fn(Form $form, string $fieldName, string|int $index, string $entryFieldName) => array_map(
                    fn($err) => $this->translator->translate($err),
                    FormExtension::collectionError($form, $fieldName, $index, $entryFieldName)
                ),
                'options' => [],
            ],
            'form' => [
                'callable' => fn(Form $form, array $params = []) => FormExtension::fullForm($form, $params),
                'options' => ['is_safe' => ['html']],
            ],
        ];
    }

    public function getFilters(): array
    {
        return [];
    }
}