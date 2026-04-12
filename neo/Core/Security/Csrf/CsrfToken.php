<?php
declare(strict_types=1);

namespace Neo\Core\Security\Csrf;

class CsrfToken
{
    private string $id;
    private string $value;
    private int $expiry;

    public function __construct(string $id, string $value, int $expiry = 3600)
    {
        $this->id = $id;
        $this->value = $value;
        $this->expiry = time() + $expiry;
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function getValue(): string
    {
        return $this->value;
    }

    public function isExpired(): bool
    {
        return time() > $this->expiry;
    }
}
