<?php
declare(strict_types=1);

namespace Neo\Core\Database\Form;

use Neo\Core\Database\Form\Type\CollectionType;
use Neo\Core\Database\Form\Type\DateType;
use Neo\Core\Database\Form\Type\TimeType;
use Neo\Core\Database\Form\Type\HiddenType;
use Neo\Core\Database\Form\Type\SelectType;
use Neo\Core\Database\ORM\Model\AbstractModel;
use Neo\Core\Http\Request;
use Neo\Core\Security\Csrf\CsrfTokenManager;
use Neo\Core\Validator\Validator;

class Form
{
    private array $fields = [];
    private ?object $data = null;
    private bool $submitted = false;
    private array $errors = [];
    private CsrfTokenManager $csrfManager;
    private string $csrfFieldName = '_csrf';
    private $ignoredFields = ['submit', '_csrf', 'csrf_token', 'reset'];
    private array $removedConstraints = [];
    private array $addedConstraints = [];
    private array $excludedFromPopulate = [];

    public function __construct()
    {
        $this->csrfManager = new CsrfTokenManager();
    }

    public function addCsrfField(): void
    {
        if (!isset($this->fields[$this->csrfFieldName])) {
            $token = $this->csrfManager->getToken($this->csrfFieldName)
                ?? $this->csrfManager->generateToken($this->csrfFieldName);

            $csrfField = new FormField(
                $this->csrfFieldName,
                new HiddenType(),
                ['value' => $token->getValue()]
            );

            $this->addField($csrfField);
        }
    }

    public function setData(AbstractModel $data): void
    {
        $this->data = clone $data;

        if (!$this->submitted) {
            foreach ($this->fields as $field) {
                $name = $field->getName();

                if ($field->getType() instanceof CollectionType) {
                    $rawValue = $data->$name;
                    $field->setValue($this->normalizeCollectionValue($rawValue));
                    continue;
                }

                $rawValue = $data->$name;
                if ($rawValue !== null) {
                    $field->setValue($rawValue);
                }
            }
        }
    }

    private function normalizeCollectionValue(mixed $value): array
    {
        if (!is_array($value) && !($value instanceof \Traversable)) {
            return [];
        }

        $result = [];
        foreach ($value as $entry) {
            if (is_array($entry)) {
                $result[] = $entry;
            } elseif (is_object($entry)) {
                $result[] = get_object_vars($entry);
            }
        }

        return $result;
    }

    public function excludeFromPopulate(array $fields): void
    {
        $this->excludedFromPopulate = array_merge($this->excludedFromPopulate, $fields);
    }

    public function populateData(): void
    {
        if (!$this->data instanceof AbstractModel) return;

        $ignoredFields = array_merge($this->ignoredFields, $this->excludedFromPopulate);

        foreach ($this->fields as $field) {
            $name = $field->getName();
            if (in_array($name, $ignoredFields, true)) continue;

            $value = $field->getValue();
            $fieldType = $field->getType();

            if (property_exists($this->data, $name)) {
                $prop = new \ReflectionProperty($this->data, $name);
                $propType = $prop->getType()?->getName();

                if ($fieldType instanceof DateType) {
                    $this->data->$name = $fieldType->normalize($value);

                } elseif ($fieldType instanceof SelectType) {
                    if ($propType === 'int') {
                        $this->data->$name = (int)$value;
                    } elseif ($propType === 'array') {
                        $this->data->$name = is_array($value) ? $value : [$value];
                    } else {
                        $this->data->$name = (string)$value;
                    }

                } elseif ($fieldType instanceof CollectionType) {
                    $this->data->$name = $fieldType->normalize($value, $field);

                } else {
                    $normalized = $fieldType->normalize($value, $field);

                    $ref = new \ReflectionProperty($this->data, $name);
                    $type = $ref->getType()?->getName();

                    if ($type === 'int') {
                        $normalized = is_numeric($normalized)
                            ? (int) $normalized
                            : 0;

                    } elseif ($type === 'float') {
                        $normalized = is_numeric($normalized)
                            ? (float) $normalized
                            : 0.0;

                    } elseif ($type === 'bool') {
                        $normalized = (bool) $normalized;

                    } elseif ($type === 'string') {
                        $normalized = (string) $normalized;
                    }

                    $this->data->$name = $normalized;
                }
            } else {
                $this->data->$name = $fieldType->normalize($value, $field);
            }

            $ref = new \ReflectionObject($this->data);
            if ($ref->hasProperty('data')) {
                $propData = $ref->getProperty('data');
                $propData->setAccessible(true);
                $internalData = $propData->getValue($this->data);
                $internalData[$name] = $this->data->$name ?? $value;
                $propData->setValue($this->data, $internalData);
            }
        }
    }

    public function addField(FormField $field): void
    {
        $this->fields[$field->getName()] = $field;
    }

    public function removeField(string $name): void
    {
        if (isset($this->fields[$name])) {
            unset($this->fields[$name]);
        }
    }

    public function getFields(): array
    {
        return $this->fields;
    }

    public function handleRequest(Request $request): void
    {
        if ($request->getMethod() === 'POST') {
            $this->submitted = true;

            $this->errors = [];
            foreach ($this->fields as $field) {
                $field->setErrors([]);
                if ($field->getType() instanceof CollectionType) {
                    $field->resetCollectionErrors();
                }
            }

            $submittedToken = $request->getPost($this->csrfFieldName) ?? '';
            $csrfField = $this->fields[$this->csrfFieldName] ?? null;

            if (!$csrfField || !$this->csrfManager->validateToken($this->csrfFieldName, $submittedToken, false)) {
                if ($csrfField) {
                    $csrfField->setErrors(['Token CSRF invalide.']);
                }
            }

            foreach ($this->fields as $field) {
                $postValue = $request->getPost($field->getName());

                if ($field->getType() instanceof SelectType
                    && $field->getOption('multiple', false) === true
                ) {
                    $field->setValue($postValue ?? []);
                    continue;
                }

                if ($field->getType() instanceof CollectionType) {
                    $field->setValue(
                        $field->getType()->normalize($postValue ?? [], $field)
                    );
                    continue;
                }

                if ($postValue !== null) {
                    $field->setValue($postValue);
                }
            }
        }
    }

    public function isSubmitted(): bool
    {
        return $this->submitted;
    }

    public function addFieldError(string $fieldName, string $message): void
    {
        if (!isset($this->fields[$fieldName])) {
            return;
        }
        $this->fields[$fieldName]->addError($message);
    }

    public function isValid(): bool
    {
        if (!$this->submitted) return false;

        foreach ($this->fields as $field) {
            $field->setErrors([]);
        }

        if ($this->data instanceof AbstractModel) {
            $ref = new \ReflectionObject($this->data);
            $propData = null;
            $internalData = [];

            if ($ref->hasProperty('data')) {
                $propData = $ref->getProperty('data');
                $propData->setAccessible(true);
                $raw = $propData->getValue($this->data);
                $internalData = is_array($raw) ? $raw : (array)$raw;
            }

            if ($propData !== null) {

                $ignoredFields = array_merge($this->ignoredFields, $this->excludedFromPopulate);

                foreach ($this->fields as $field) {
                    $name = $field->getName();
                    if (in_array($name, $ignoredFields, true)) continue;

                    $value = $field->getValue();
                    $fieldType = $field->getType();

                    if (property_exists($this->data, $name)) {
                        $prop = new \ReflectionProperty($this->data, $name);
                        $propType = $prop->getType()?->getName();

                        if ($fieldType instanceof TimeType) {
                            $this->data->$name = $fieldType->normalize($value);
                        } elseif ($fieldType instanceof SelectType) {
                            if ($propType === 'int') {
                                $this->data->$name = (int)$value;
                            } elseif ($propType === 'array') {
                                $this->data->$name = is_array($value) ? $value : [$value];
                            } else {
                                $this->data->$name = (string)$value;
                            }
                        } else {
                            $this->data->$name = $fieldType->normalize($value);
                        }
                    }
                    $internalData[$name] = $this->data->$name ?? $value;
                }
                $propData->setValue($this->data, $internalData);
            }

            $validator = new Validator();
            $modelErrors = $validator->validate($this->data, $this);

            foreach ($this->fields as $field) {
                $name = $field->getName();
                $fieldErrors = $modelErrors[$name] ?? [];
                if (!empty($fieldErrors)) {
                    $field->setErrors($fieldErrors);
                }
            }

            foreach ($this->fields as $field) {
                if (!($field->getType() instanceof CollectionType)) {
                    continue;
                }

                $collectionErrors = $field->getType()->validateEntries($field, $validator);

                foreach ($collectionErrors as $key => $messages) {
                    foreach ($messages as $message) {
                        $field->addCollectionError($key, $message);
                    }
                }
            }
        }

        $hasFieldErrors = false;
        foreach ($this->fields as $field) {
            if (!empty($field->getErrors())) {
                $hasFieldErrors = true;
            }
            if (!empty($field->getCollectionErrors())) {
                $hasFieldErrors = true;
            }
        }

        return empty($this->errors) && !$hasFieldErrors;
    }

    public function getData(): ?AbstractModel
    {
        return $this->data instanceof AbstractModel ? $this->data : null;
    }

    public function setError(string $message): void
    {
        $this->errors[] = $message;
    }

    public function getErrors(): array
    {
        return $this->errors;
    }

    public function hasErrors(): bool
    {
        foreach ($this->fields as $field) {
            if (!empty($field->getErrors())) {
                return true;
            }
        }

        return !empty($this->errors);
    }

    public function getField(string $name): ?FormField
    {
        return $this->fields[$name] ?? null;
    }

    public function get(string $name): mixed
    {
        return $this->fields[$name]?->getValue();
    }

    public function addConstraint(string $fieldName, object $constraint): void
    {
        $this->addedConstraints[$fieldName][] = $constraint;
    }

    public function getAddedConstraints(string $fieldName): array
    {
        return $this->addedConstraints[$fieldName] ?? [];
    }

    public function removeConstraint(string $fieldName, string $constraintClass): void
    {
        $this->removedConstraints[$fieldName][] = $constraintClass;
    }

    public function isConstraintRemoved(string $fieldName, string $constraintClass): bool
    {
        return isset($this->removedConstraints[$fieldName])
            && in_array($constraintClass, $this->removedConstraints[$fieldName], true);
    }
}
