<?php
declare(strict_types = 1);

namespace Neo\Core\Routing\Attribute;

use Attribute;

#[Attribute(Attribute::TARGET_CLASS | Attribute::TARGET_METHOD)]
class Maintenance
{
    public string $message;

    public function __construct(string $message = 'Maintenance en cours.')
    {
        $this->message = $message;
    }
}