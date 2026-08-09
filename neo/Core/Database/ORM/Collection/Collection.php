<?php

namespace Neo\Core\Database\ORM\Collection;

use ArrayAccess;
use ArrayIterator;
use Countable;
use IteratorAggregate;
use Traversable;

/**
 * @template TKey of array-key
 * @template TValue
 *
 * @implements IteratorAggregate<TKey, TValue>
 * @implements ArrayAccess<TKey, TValue>
 */
class Collection implements Countable, IteratorAggregate, ArrayAccess
{
    /**
     * @param array<TKey, TValue> $elements
     */
    public function __construct(
        protected array $elements = []
    ) {
    }

    /**
     * @return array<TKey, TValue>
     */
    public function toArray(): array
    {
        $this->initialize();
        return $this->elements;
    }

    /**
     * @param TValue $element
     */
    public function add(mixed $element): void
    {
        $this->initialize();
        $this->elements[] = $element;
    }

    /**
     * @param TValue $element
     */
    public function contains(mixed $element): bool
    {
        $this->initialize();
        return in_array($element, $this->elements, true);
    }

    /**
     * @param TKey $key
     * @return TValue|null
     */
    public function remove(int|string $key): mixed
    {
        $this->initialize();
        $removed = $this->elements[$key] ?? null;
        unset($this->elements[$key]);
        return $removed;
    }

    /**
     * @param TValue $element
     */
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

    /**
     * @return TValue|null
     */
    public function first(): mixed
    {
        $this->initialize();
        return array_first($this->elements);
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

    protected function initialize(): void {}
}