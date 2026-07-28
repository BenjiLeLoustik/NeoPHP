<?php

namespace Neo\Core\Database\ORM\Collection;

use ArrayAccess;
use ArrayIterator;
use Countable;
use IteratorAggregate;
use Traversable;

class Collection implements Countable, IteratorAggregate, ArrayAccess
{
    public function __construct(
        protected array $elements = []
    ) {
    }

    public function toArray(): array
    {
        $this->initialize();
        return $this->elements;
    }

    public function add(mixed $element): void
    {
        $this->initialize();
        $this->elements[] = $element;
    }

    public function contains(mixed $element): bool
    {
        $this->initialize();
        return in_array($element, $this->elements, true);
    }

    public function remove(int|string $key): mixed
    {
        $this->initialize();
        $removed = $this->elements[$key] ?? null;
        unset($this->elements[$key]);
        return $removed;
    }

    public function removeElement(mixed $element): bool
    {
        $this->initialize();
        $key = array_search($element, $this->elements, true);
        if ($key === false) {
            return false;
        }
        unset($this->elements[$key]);
        return true;
    }

    public function first(): mixed
    {
        $this->initialize();
        return $this->elements[array_key_first($this->elements) ?? 0] ?? null;
    }

    public function isEmpty(): bool
    {
        $this->initialize();
        return $this->elements === [];
    }

    public function count(): int
    {
        $this->initialize();
        return count($this->elements);
    }

    public function getIterator(): Traversable
    {
        $this->initialize();
        return new ArrayIterator($this->elements);
    }

    public function offsetExists(mixed $offset): bool
    {
        $this->initialize();
        return isset($this->elements[$offset]);
    }

    public function offsetGet(mixed $offset): mixed
    {
        $this->initialize();
        return $this->elements[$offset] ?? null;
    }

    public function offsetSet(mixed $offset, mixed $value): void
    {
        $this->initialize();
        if ($offset === null) {
            $this->elements[] = $value;
        } else {
            $this->elements[$offset] = $value;
        }
    }

    public function offsetUnset(mixed $offset): void
    {
        $this->initialize();
        unset($this->elements[$offset]);
    }

    protected function initialize(): void
    {
    }
}