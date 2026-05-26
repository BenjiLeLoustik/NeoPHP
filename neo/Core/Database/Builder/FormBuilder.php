<?php
declare(strict_types=1);

namespace Neo\Core\Database\Builder;

use Neo\Core\Database\Exception\DatabaseException;
use Neo\Core\Database\Form\Form;
use Neo\Core\Database\Form\FormField;
use Neo\Core\Database\Form\Type\AbstractType;
use Neo\Core\Database\Form\Type\TextType;
use Neo\Core\Database\ORM\Model\AbstractModel;

class FormBuilder
{
    private Form $form;
    private AbstractModel $model;

    public function __construct(AbstractModel|string $model)
    {
        if (is_string($model)) {
            if (!class_exists($model)) {
                throw new DatabaseException(
                    title: 'Form Builder Error',
                    message: sprintf("Class '%s' does not exist.", $model),
                    code: 500
                );
            }
            $model = new $model();
        }

        $this->model = clone $model;
        $this->model->trackIdentity = false;

        $this->form = new Form();
        $this->form->setData($this->model);
    }

    public function add(string $name, string|AbstractType $type, array $options = []): self
    {
        $typeInstance = is_string($type) ? new $type() : $type;
        $field = new FormField($name, $typeInstance, $options);
        $this->form->addField($field);
        return $this;
    }

    public function auto(array $fieldTypes = [], array $excludeFields = []): self
    {
        $refClass = new \ReflectionClass($this->model);
        $wantedFields = array_keys($fieldTypes);

        $defaultExcluded = [
            $this->model::getPrimaryKey(),
            'created_at',
            'updated_at',
            'deleted_at',
        ];

        $relations = $this->model->getRelations();

        foreach ($refClass->getProperties(\ReflectionProperty::IS_PUBLIC) as $prop) {
            $name = $prop->getName();

            if (
                in_array($name, array_merge($excludeFields, $defaultExcluded), true) ||
                isset($relations[$name])
            ) {
                continue;
            }

            if (!empty($wantedFields) && !in_array($name, $wantedFields, true)) {
                continue;
            }

            $propType = $prop->getType()?->getName();
            if ($propType === 'array' && !isset($fieldTypes[$name])) {
                continue;
            }

            $typeClass = TextType::class;
            $options = [
                'label' => ucfirst($name),
                'value' => $prop->isInitialized($this->model) ? $prop->getValue($this->model) : null,
            ];

            if (isset($fieldTypes[$name])) {
                if (is_string($fieldTypes[$name])) {
                    $typeClass = $fieldTypes[$name];
                } elseif (is_array($fieldTypes[$name])) {
                    $typeClass = $fieldTypes[$name][0] ?? TextType::class;
                    $options = array_merge($options, $fieldTypes[$name][1] ?? []);
                }
            }

            $this->form->addField(new FormField($name, new $typeClass(), $options));
        }

        return $this;
    }

    public function generate(): Form
    {
        return $this->form;
    }
}