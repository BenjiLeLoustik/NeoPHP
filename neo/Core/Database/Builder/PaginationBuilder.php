<?php
declare(strict_types=1);

namespace Neo\Core\Database\Builder;

class PaginationBuilder
{
    /** @var array<int, mixed> */
    private array $items;
    private int $total;
    private int $perPage;
    private int $currentPage;

    /**
     * @param array<int, mixed> $items
     */
    public function __construct(
        array $items,
        int $total,
        int $perPage,
        int $currentPage,
    ) {
        $this->items = $items;
        $this->total = $total;
        $this->perPage = $perPage;
        $this->currentPage = $currentPage;
    }

    /**
     * @return array<int, mixed>
     */
    public function getItems(): array
    {
        return $this->items;
    }

    public function getTotal(): int
    {
        return $this->total;
    }

    public function getPerPage(): int
    {
        return $this->perPage;
    }

    public function getCurrentPage(): int
    {
        return $this->currentPage;
    }

    public function getLastPage(): int
    {
        return (int) ceil($this->total / $this->perPage);
    }

    public function hasNextPage(): bool
    {
        return $this->currentPage < $this->getLastPage();
    }

    public function hasPreviousPage(): bool
    {
        return $this->currentPage > 1;
    }

    /**
     * @return array{items: array<int, mixed>, total: int, per_page: int, current_page: int, last_page: int, has_next: bool, has_previous: bool}
     */
    public function toArray(): array
    {
        return [
            'items' => $this->items,
            'total' => $this->total,
            'per_page' => $this->perPage,
            'current_page' => $this->currentPage,
            'last_page' => $this->getLastPage(),
            'has_next' => $this->hasNextPage(),
            'has_previous' => $this->hasPreviousPage(),
        ];
    }
}