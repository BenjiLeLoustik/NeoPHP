<?php
declare(strict_types=1);

namespace Neo\Core\Database\ORM\Mapping\Attribute;

use Attribute;

#[Attribute(Attribute::TARGET_PROPERTY)]
final class Column
{
    public function __construct(
        public string $type = 'string',
        public ?string $name = null,
        public ?int $length = null,
        public bool $nullable = false,
        public bool $unique = false,
        public mixed $default = null,
        public ?int $precision = null,
        public ?int $scale = null,
        public bool $unsigned = false,
        public ?string $columnDefinition = null,
        public array $values = [],
        public ?string $enumClass = null,
    ) {
    }
}