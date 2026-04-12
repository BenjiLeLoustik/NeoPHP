<?php
declare(strict_types=1);

namespace Neo\Core\Routing\Attribute;

use Attribute;

#[Attribute(Attribute::TARGET_CLASS)]
class MainRoute
{
    public string $path;
    public string $name;

    public function __construct(string $path, string $name)
    {
        $this->path = rtrim($path, '/');
        $this->name = $name;
    }
}
