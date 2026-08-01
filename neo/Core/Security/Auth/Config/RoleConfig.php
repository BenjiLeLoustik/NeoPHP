<?php
declare(strict_types=1);

namespace Neo\Core\Security\Auth\Config;

class RoleConfig
{
    /**
     * @param class-string $model
     */
    public function __construct(
        private string $relation,
        private string $model,
        private string $field,
    ) {
    }

    public function getRelation(): string
    {
        return $this->relation;
    }

    /**
     * @return class-string
     */
    public function getModel(): string
    {
        return $this->model;
    }

    public function getField(): string
    {
        return $this->field;
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): ?self
    {
        if (
            !isset($data['relation'], $data['model'], $data['field'])
            || !is_string($data['relation'])
            || !is_string($data['model'])
            || !is_string($data['field'])
        ) {
            return null;
        }

        /** @var class-string $model */
        $model = $data['model'];

        return new self($data['relation'], $model, $data['field']);
    }
}