<?php
declare(strict_types=1);

namespace Neo\Core\Database\ORM\Mapping\Attribute;

use Attribute;

#[Attribute(Attribute::TARGET_PROPERTY)]
final class OneToMany
{
    public function __construct(
        public string $targetEntity,
        public string $mappedBy,
        public string $fetch = 'LAZY',
        public array $cascade = [],
        public bool $orphanRemoval = false,
    ) {
    }
}