<?php

declare(strict_types=1);

namespace Neo\Core\Database\Form\Abstract;

use Neo\Core\Database\Form\Form;
use Neo\Core\Database\Form\FormBuilder;
use Neo\Core\Database\Form\FormFactory;
use Neo\Core\Http\Request\Enum\HttpRequest;
use Neo\Core\Http\Request\Request;

abstract class AbstractForm
{
    private ?Form $form = null;

    public function __construct(
        protected FormFactory $factory,
        protected Request $request,
    ) {
    }

    abstract public function getName(): string;

    abstract public function buildForm(FormBuilder $builder): void;

    protected function getDefaultEntity(): ?object
    {
        return null;
    }

    public function handle(?object $entity = null): Form
    {
        if ($this->form !== null) {
            return $this->form;
        }

        $entity ??= $this->getDefaultEntity();

        $builder = $entity !== null
            ? $this->factory->createFor($entity, $this->getName())
            : $this->factory->create($this->getName());

        $this->buildForm($builder);

        $form = $builder->getForm();

        if ($this->request->isMethod(HttpRequest::POST)) {
            $form->handleRequest($this->request->getPostAll());
        }

        return $this->form = $form;
    }
}