<?php
declare(strict_types=1);

namespace Neo\Core\Security\Middleware\Attribute;

use Attribute;

#[Attribute(Attribute::TARGET_CLASS | Attribute::TARGET_METHOD)]
class IsGranted
{
    /**
     * @param array<int, string> $roles
     */
    public function __construct(
        public array $roles,
        public string $message = '',
        public string $onError = 'block',
        public ?string $redirect = null,
    ) {
    }
}
