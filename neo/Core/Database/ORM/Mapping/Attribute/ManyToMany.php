<?php
declare(strict_types=1);

namespace Neo\Core\Database\ORM\Mapping\Attribute;

use Attribute;

#[Attribute(Attribute::TARGET_PROPERTY)]
final class ManyToMany
{
    public function __construct(
        public string $targetEntity,
        public ?string $mappedBy = null,
        public ?string $inversedBy = null,
        public string $fetch = 'LAZY',
        public array $cascade = [],
    ) {
    }
}