<?php

declare(strict_types=1);

namespace Neo\Core\Database\Collector;

use Neo\Core\Database\Form\FormFactory;
use Neo\Core\Profiler\Interface\CollectorInterface;
use Neo\Core\Tools\Debug\Dumper;

final class FormsCollector implements CollectorInterface
{
    public function getName(): string
    {
        return 'forms';
    }

    public function collect(): array
    {
        $builders = FormFactory::getBuilders();
        $forms = [];

        foreach ($builders as $builder) {
            $form = $builder->getForm();

            $forms[] = [
                'name' => $form->getName(),
                'submitted' => $form->isSubmitted(),
                'valid' => $form->isSubmitted() ? $form->isValid() : null,
                'entityClass' => $form->getEntity() !== null ? $form->getEntity()::class : null,
                'fields' => array_keys($form->getFields()),
                'errors' => $form->getErrors(),
                'data' => $form->getData(),
            ];
        }

        $submittedCount = $forms
                |> (fn (array $f): array => array_filter($f, static fn (array $x) => $x['submitted']))
                |> count(...);

        $invalidCount = $forms
                |> (fn (array $f): array => array_filter($f, static fn (array $x) => $x['submitted'] && $x['valid'] === false))
                |> count(...);

        return [
            'total' => count($forms),
            'submittedCount' => $submittedCount,
            'invalidCount' => $invalidCount,
            'forms' => $forms,
        ];
    }

    public function inToolbar(): bool
    {
        return false;
    }

    public function inProfiler(): bool
    {
        return true;
    }

    public function toolbarData(): array
    {
        return [
            'label' => 'Forms',
            'value' => '',
            'badge' => null,
        ];
    }

    public function profilerData(): array
    {
        $data = $this->collect();

        if ($data['total'] === 0) {
            return [
                'title' => 'Forms',
                'group' => 'Database',
                'badge' => null,
                'metrics' => [
                    ['label' => 'Total forms', 'value' => '0'],
                ],
                'blocks' => [
                    [
                        'type' => 'kv',
                        'section' => null,
                        'rows' => [
                            ['label' => 'Status', 'value' => 'No form was created during this request.'],
                        ],
                    ],
                ],
            ];
        }

        $tabs = [];

        foreach ($data['forms'] as $form) {
            $status = !$form['submitted']
                ? 'Not submitted'
                : ($form['valid'] ? 'Valid' : 'Invalid');

            $errorRows = [];
            foreach ($form['errors'] as $field => $messages) {
                foreach ($messages as $message) {
                    $errorRows[] = [$field, $message];
                }
            }

            $tabs[] = [
                'label' => $form['name'],
                'badge' => $form['submitted'] && !$form['valid'] ? '!' : null,
                'badgeType' => 'alert',
                'blocks' => [
                    [
                        'type' => 'kv',
                        'section' => null,
                        'rows' => [
                            ['label' => 'Status', 'value' => $status],
                            ['label' => 'Bound entity', 'value' => $form['entityClass'] ?? 'n/a'],
                            ['label' => 'Fields', 'value' => implode(', ', $form['fields'])],
                        ],
                    ],
                    [
                        'type' => 'table',
                        'section' => 'Validation errors',
                        'columns' => ['Field', 'Message'],
                        'rows' => $errorRows,
                    ],
                    [
                        'type' => 'raw-html',
                        'section' => 'Submitted data',
                        'html' => new Dumper()->render([$form['data']], false),
                    ],
                ],
            ];
        }

        return [
            'title' => 'Forms',
            'group' => 'Database',
            'badge' => $data['invalidCount'] > 0 ? (string) $data['invalidCount'] : null,
            'badgeType' => 'alert',
            'metrics' => [
                ['label' => 'Total forms', 'value' => (string) $data['total']],
                ['label' => 'Submitted', 'value' => (string) $data['submittedCount']],
                ['label' => 'Invalid', 'value' => (string) $data['invalidCount']],
            ],
            'blocks' => [
                ['type' => 'tabs', 'section' => null, 'tabs' => $tabs],
            ],
        ];
    }
}