<?php
declare(strict_types=1);

namespace Neo\Core\Database\Form;

use Neo\Core\Security\Csrf\CsrfManager;
use Neo\Core\Validator\ValidatorManager;

final class FormFactory
{
    private readonly PropertyAccessor $accessor;

    /** @var list<FormBuilder> */
    private static array $builders = [];

    public function __construct(
        private ValidatorManager $validator,
        private ?CsrfManager $csrf = null,
        ?PropertyAccessor $accessor = null,
    ) {
        $this->accessor = $accessor ?? new PropertyAccessor();
    }

    public function create(string $name = 'form'): FormBuilder
    {
        $builder = new FormBuilder($name, $this->accessor, $this->validator, $this->csrf, null);
        self::$builders[] = $builder;
        return $builder;
    }

    public function createFor(object $entity, string $name = 'form'): FormBuilder
    {
        $builder = new FormBuilder($name, $this->accessor, $this->validator, $this->csrf, $entity);
        self::$builders[] = $builder;
        return $builder;
    }

    /**
     * @return list<FormBuilder>
     */
    public static function getBuilders(): array
    {
        return self::$builders;
    }
}