<?php
declare(strict_types=1);

namespace Neo\Core\Database\ORM\Mapping\Attribute;

use Attribute;

#[Attribute(Attribute::TARGET_PROPERTY)]
final class JoinTable
{
    public function __construct(
        public ?string $name = null,
        public array $joinColumns = [],
        public array $inverseJoinColumns = [],
    ) {
    }
}