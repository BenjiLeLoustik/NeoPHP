<?php
declare(strict_types=1);

namespace Neo\Core\Database\ORM\Mapping\Attribute;

use Attribute;

#[Attribute(Attribute::TARGET_PROPERTY | Attribute::IS_REPEATABLE)]
final class JoinColumn
{
    public function __construct(
        public ?string $name = null,
        public string $referencedColumnName = 'id',
        public bool $nullable = true,
        public bool $unique = false,
        public ?string $onDelete = null,
        public ?string $onUpdate = null,
    ) {
    }
}