<?php
declare(strict_types=1);

namespace Neo\Core\Security\Middleware\Attribute;

use Attribute;

#[Attribute(Attribute::TARGET_CLASS | Attribute::TARGET_METHOD | Attribute::IS_REPEATABLE)]
class Middleware
{
    /**
     * @param array<string, mixed> $params
     */
    public function __construct(
        public string $use,
        public string $message = '',
        public string $onError = 'block',
        public ?string $redirect = null,
        public array $params = [],
        public int $priority = 0
    ) {
    }
}
