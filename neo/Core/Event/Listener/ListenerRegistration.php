<?php
declare(strict_types=1);

namespace Neo\Core\Event\Listener;

class ListenerRegistration implements \JsonSerializable
{
    /**
     * @param class-string $class
     * @param string|array{0: string, 1?: int} $method
     */
    public function __construct(
        private string $class,
        private int $priority,
        private string|array $method = 'handle',
        private ?object $instance = null,
    ) {
    }

    /**
     * @return class-string
     */
    public function getClass(): string
    {
        return $this->class;
    }

    public function getPriority(): int
    {
        return $this->priority;
    }

    /**
     * @return string|array{0: string, 1?: int}
     */
    public function getMethod(): string|array
    {
        return $this->method;
    }

    public function getInstance(): ?object
    {
        return $this->instance;
    }

    public function resolveMethodName(): string
    {
        return is_array($this->method) ? $this->method[0] : $this->method;
    }

    /**
     * @param array{class: class-string, priority: int, method?: string|array{0: string, 1?: int}} $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            class: $data['class'],
            priority: $data['priority'] ?? 0,
            method: $data['method'] ?? 'handle',
        );
    }

    /**
     * @return array{class: class-string, priority: int, method: string|array{0: string, 1?: int}}
     */
    public function jsonSerialize(): array
    {
        return [
            'class' => $this->class,
            'priority' => $this->priority,
            'method' => $this->method,
        ];
    }
}