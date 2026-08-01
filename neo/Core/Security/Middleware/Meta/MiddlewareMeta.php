<?php

declare(strict_types=1);

namespace Neo\Core\Security\Middleware\Meta;

final class MiddlewareMeta
{
    /**
     * @param class-string $class
     * @param array<string, mixed> $params
     */
    public function __construct(
        public string $class,
        public string $message,
        public string $onError,
        public ?string $redirect,
        public bool $isClass,
        public array $params,
        public int $priority,
    ) {
    }

    /**
     * @return class-string
     */
    public function getClass(): string
    {
        return $this->class;
    }

    public function getMessage(): string
    {
        return $this->message;
    }

    public function getOnError(): string
    {
        return $this->onError;
    }

    public function getRedirect(): ?string
    {
        return $this->redirect;
    }

    public function isClass(): bool
    {
        return $this->isClass;
    }

    /**
     * @return array<string, mixed>
     */
    public function getParams(): array
    {
        return $this->params;
    }

    public function getPriority(): int
    {
        return $this->priority;
    }
}