<?php
declare(strict_types=1);

namespace Neo\Core\Database\ORM\Mapping\Attribute;

use Attribute;

#[Attribute(Attribute::TARGET_PROPERTY)]
final class GeneratedValue
{
    public const string AUTO = 'AUTO';
    public const string IDENTITY = 'IDENTITY';
    public const string NONE = 'NONE';

    public function __construct(
        public string $strategy = self::AUTO
    ) {
    }
}