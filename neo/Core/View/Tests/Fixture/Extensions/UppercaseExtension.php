<?php

namespace Neo\Core\View\Tests\Fixture\Extensions;

use Neo\Core\View\Interface\TwigExtensionInterface;

class UppercaseExtension implements TwigExtensionInterface
{
    public function getFunctions(): array
    {
        return [
            'shout' => fn(string $value) => strtoupper($value) . '!',
        ];
    }

    public function getFilters(): array
    {
        return [
            'reverse_str' => fn(string $value) => strrev($value),
        ];
    }
}