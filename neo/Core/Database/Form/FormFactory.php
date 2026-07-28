<?php
declare(strict_types=1);

namespace Neo\Core\Database\Form;

use Neo\Core\Security\Csrf\CsrfManager;
use Neo\Core\Validator\ValidatorManager;

final class FormFactory
{
    private readonly PropertyAccessor $accessor;

    public function __construct(
        private readonly ValidatorManager $validator,
        private readonly ?CsrfManager $csrf = null,
        ?PropertyAccessor $accessor = null,
    ) {
        $this->accessor = $accessor ?? new PropertyAccessor();
    }

    public function create(string $name = 'form'): FormBuilder
    {
        return new FormBuilder($name, $this->accessor, $this->validator, $this->csrf, null);
    }

    public function createFor(object $entity, string $name = 'form'): FormBuilder
    {
        return new FormBuilder($name, $this->accessor, $this->validator, $this->csrf, $entity);
    }
}