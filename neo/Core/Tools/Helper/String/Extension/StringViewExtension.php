<?php

namespace Neo\Core\Tools\Helper\String\Extension;

use Neo\Core\Extension\Attribute\Extension;
use Neo\Core\Extension\Enum\ExtensionTypeEnum;
use Neo\Core\Tools\Helper\String\StringHelper;
use Neo\Core\View\Interface\TwigExtensionInterface;

#[Extension(type: ExtensionTypeEnum::VIEW)]
class StringViewExtension implements TwigExtensionInterface
{

    /**
     * @return array<string, array{callable: callable, options: array<string, mixed>}>
     */
    public function getFunctions(): array
    {
        return [
            'slugify' => [
                'callable' => fn(string $value) => StringHelper::slugify($value),
                'options' => []
            ]
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function getFilters(): array
    {
        return [
            'slugify' => [
                'callable' => fn(string $value) => StringHelper::slugify($value),
                'options' => []
            ]
        ];
    }
}