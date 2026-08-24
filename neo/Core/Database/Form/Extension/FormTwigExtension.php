<?php
declare(strict_types=1);

namespace Neo\Core\Database\Form\Extension;

use Neo\Core\Database\Form\Form;
use Neo\Core\Database\Form\FormRenderer;
use Neo\Core\Extension\Attribute\Extension;
use Neo\Core\Extension\Enum\ExtensionTypeEnum;
use Neo\Core\Validator\Assert\Length;
use Neo\Core\Validator\Assert\Regex;
use Neo\Core\View\Interface\TwigExtensionInterface;

#[Extension(type: ExtensionTypeEnum::VIEW)]
final class FormTwigExtension implements TwigExtensionInterface
{
    private FormRenderer $renderer;

    public function __construct(
        ?FormRenderer $renderer = null
    ) {
        $this->renderer = $renderer ?? new FormRenderer();
    }

    public function getFunctions(): array
    {
        $html = ['is_safe' => ['html']];

        return [
            'form' => [
                'callable' => fn(Form $form, string $action = '', string $method = 'POST', array $attr = []): string
                => $this->renderer->render($form, $action, $method, $attr),
                'options' => $html,
            ],
            'form_start' => [
                'callable' => fn(Form $form, string $action = '', string $method = 'POST', array $attr = []): string
                => $this->renderer->start($form, $action, $method, $attr),
                'options' => $html,
            ],
            'form_end' => [
                'callable' => fn(): string => $this->renderer->end(),
                'options' => $html,
            ],
            'form_row' => [
                'callable' => fn(Form $form, string $field): string => $this->render($form, $field, 'field'),
                'options' => $html,
            ],
            'form_label' => [
                'callable' => fn(Form $form, string $field): string => $this->render($form, $field, 'label'),
                'options' => $html,
            ],
            'form_widget' => [
                'callable' => fn(Form $form, string $field): string => $this->render($form, $field, 'widget'),
                'options' => $html,
            ],
            'form_errors' => [
                'callable' => fn(Form $form, string $field): string => $this->render($form, $field, 'errors'),
                'options' => $html,
            ],
            'form_error' => [
                'callable' => fn(Form $form, string $field): ?string => $form->getField($field)?->getErrors()[0] ?? null,
                'options' => []
            ],
            'field_checks' => [
                'callable' => function (Form $form, string $field): array {
                    $checks = [];

                    foreach ($form->getAddedConstraints($field) as $constraint) {
                        if ($constraint instanceof Regex && $constraint->checklistLabel !== null) {
                            $checks[] = [
                                'rule' => strtolower(str_replace(' ', '_', $constraint->checklistLabel)),
                                'label' => $constraint->checklistLabel,
                                'pattern' => trim($constraint->pattern, '/'),
                            ];
                        }
                    }

                    return $checks;
                },
                'options' => []
            ],
            'field_failed_rules' => [
                'callable' => function (Form $form, string $field): array {
                    $formField = $form->getField($field);
                    if ($formField === null) {
                        return [];
                    }

                    $failedMessages = $formField->getErrors();
                    $failedRules = [];

                    foreach ($form->getAddedConstraints($field) as $constraint) {
                        if ($constraint instanceof Regex && $constraint->checklistLabel !== null) {
                            $rule = strtolower(str_replace(' ', '_', $constraint->checklistLabel));
                            if (in_array($constraint->getMessage(), $failedMessages, true)) {
                                $failedRules[] = $rule;
                            }
                        }
                    }

                    return $failedRules;
                },
                'options' => [],
            ]
        ];
    }

    public function getFilters(): array
    {
        return [];
    }

    private function render(Form $form, string $field, string $part): string
    {
        $target = $form->getField($field);
        if ($target === null) {
            return '';
        }

        return match ($part) {
            'label'  => $this->renderer->label($target),
            'widget' => $this->renderer->widget($target),
            'errors' => $this->renderer->errors($target),
            default  => $this->renderer->field($target),
        };
    }
}