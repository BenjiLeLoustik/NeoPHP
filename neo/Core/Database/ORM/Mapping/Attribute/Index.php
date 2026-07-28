<?php
declare(strict_types=1);

namespace Neo\Core\Database\ORM\Mapping\Attribute;

use Attribute;

#[Attribute(Attribute::TARGET_CLASS | Attribute::IS_REPEATABLE)]
final class Index
{
    /**
     * @param list<string> $columns
     */
    public function __construct(
        public array $columns,
        public ?string $name = null,
    ) {
    }
}