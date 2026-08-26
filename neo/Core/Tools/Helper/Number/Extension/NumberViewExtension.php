<?php
declare(strict_types=1);

namespace Neo\Core\Tools\Helper\Number\Extension;

use Neo\Core\Extension\Attribute\Extension;
use Neo\Core\Extension\Enum\ExtensionTypeEnum;
use Neo\Core\Tools\Helper\Number\NumberHelper;
use Neo\Core\View\Interface\TwigExtensionInterface;

#[Extension(type: ExtensionTypeEnum::VIEW)]
final class NumberViewExtension implements TwigExtensionInterface
{
    /**
     * @return array<string, array{callable: callable, options: array<string, mixed>}>
     */
    public function getFunctions(): array
    {
        return [
            'compact_number' => [
                'callable' => fn(int|float $number, int $precision = 1) => NumberHelper::compact($number, $precision),
                'options' => [],
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function getFilters(): array
    {
        return [
            'compact_number' => [
                'callable' => fn(int|float $number, int $precision = 1) => NumberHelper::compact($number, $precision),
                'options' => [],
            ],
        ];
    }
}