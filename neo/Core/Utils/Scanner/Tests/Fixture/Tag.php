<?php
declare(strict_types=1);

namespace Neo\Core\Utils\Scanner\Tests\Fixture;

use Attribute;

#[Attribute(Attribute::TARGET_CLASS | Attribute::TARGET_METHOD | Attribute::TARGET_PROPERTY | Attribute::TARGET_PARAMETER)]
final class Tag
{
    public function __construct(
        public readonly string $value
    ) {}
}