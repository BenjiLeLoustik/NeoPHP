<?php
declare(strict_types=1);

namespace Neo\Core\Extension\Attribute;

use Attribute;
use Neo\Core\Extension\Enum\ExtensionTypeEnum;

#[Attribute(Attribute::TARGET_CLASS)]
final class Extension
{
    public function __construct(
        public ExtensionTypeEnum $type,
    ) {}
}