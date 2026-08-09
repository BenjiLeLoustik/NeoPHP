<?php

declare(strict_types=1);

namespace Neo\Core\Database\Pagination;

use ArrayIterator;
use Countable;
use IteratorAggregate;

/**
 * @template T
 * @implements IteratorAggregate<int, T>
 */
class Paginator implements IteratorAggregate, Countable
{
    /**
     * @param list<T> $items
     */
    public function __construct(
        private array $items,
        private int $totalItems,
        private int $currentPage,
        private int $perPage,
    ) {
    }

    /**
     * @return list<T>
     */
    public function getItems(): array
    {
        return $this->items;
    }

    public function getCurrentPage(): int
    {
        return $this->currentPage;
    }

    public function getPerPage(): int
    {
        return $this->perPage;
    }

    public function getTotalItems(): int
    {
        return $this->totalItems;
    }

    public function getTotalPages(): int
    {
        return $this->perPage > 0 ? (int) ceil($this->totalItems / $this->perPage) : 0;
    }

    public function hasNextPage(): bool
    {
        return $this->currentPage < $this->getTotalPages();
    }

    public function hasPreviousPage(): bool
    {
        return $this->currentPage > 1;
    }

    public function getNextPage(): ?int
    {
        return $this->hasNextPage() ? $this->currentPage + 1 : null;
    }

    public function getPreviousPage(): ?int
    {
        return $this->hasPreviousPage() ? $this->currentPage - 1 : null;
    }

    /**
     * @return list<int|null>
     */
    public function getLinks(int $onEachSide = 2): array
    {
        $total = $this->getTotalPages();

        if ($total <= 1) {
            return $total === 1 ? [1] : [];
        }

        $current = $this->currentPage;
        $start = max(1, $current - $onEachSide);
        $end = min($total, $current + $onEachSide);

        $pages = [];

        if ($start > 1) {
            $pages[] = 1;
            if ($start > 2) {
                $pages[] = null;
            }
        }

        for ($i = $start; $i <= $end; $i++) {
            $pages[] = $i;
        }

        if ($end < $total) {
            if ($end < $total - 1) {
                $pages[] = null;
            }
            $pages[] = $total;
        }

        return $pages;
    }

    public function count(): int
    {
        return count($this->items);
    }

    /**
     * @return ArrayIterator<int, T>
     */
    public function getIterator(): ArrayIterator
    {
        return new ArrayIterator($this->items);
    }
}