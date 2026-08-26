<?php
declare(strict_types=1);

namespace Neo\Core\Tools\Helper\Date\Extension;

use DateTimeInterface;
use Neo\Core\Extension\Attribute\Extension;
use Neo\Core\Extension\Enum\ExtensionTypeEnum;
use Neo\Core\Tools\Helper\Date\DateHelper;
use Neo\Core\View\Interface\TwigExtensionInterface;

#[Extension(type: ExtensionTypeEnum::VIEW)]
final class DateViewExtension implements TwigExtensionInterface
{
    /**
     * @return array<string, array{callable: callable, options: array<string, mixed>}>
     */
    public function getFunctions(): array
    {
        return [
            'time_ago' => [
                'callable' => fn(DateTimeInterface|string $datetime) => DateHelper::timeAgo($datetime),
                'options' => [],
            ],
        ];
    }

    /**
     * @return array<string, array{callable: callable, options: array<string, mixed>}>
     */
    public function getFilters(): array
    {
        return [
            'time_ago' => [
                'callable' => fn(DateTimeInterface|string $datetime) => DateHelper::timeAgo($datetime),
                'options' => [],
            ],
        ];
    }
}