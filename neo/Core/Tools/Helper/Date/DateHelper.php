<?php
declare(strict_types=1);

namespace Neo\Core\Tools\Helper\Date;

use DateMalformedStringException;
use DateTime;
use DateTimeInterface;
use Neo\Core\Tools\Helper\Date\Enum\TimeAgoEnum;

final class DateHelper
{
    /**
     * @return array{unit: TimeAgoEnum, count: int}
     * @throws DateMalformedStringException
     */
    public static function timeAgo(DateTimeInterface|string $datetime): array
    {
        if (is_string($datetime)) {
            $datetime = new DateTime($datetime);
        }

        $now = new DateTime();
        $diff = $now->getTimestamp() - $datetime->getTimestamp();

        if ($diff < 5) {
            return ['unit' => TimeAgoEnum::JUST_NOW->translate(), 'count' => 0];
        }

        if ($diff < 60) {
            return ['unit' => TimeAgoEnum::SECONDS_AGO->translate(), 'count' => $diff];
        }

        $minutes = (int) floor($diff / 60);
        if ($minutes < 60) {
            return [
                'unit' => ($minutes === 1)
                    ? TimeAgoEnum::MINUTE_AGO->translate()
                    : TimeAgoEnum::MINUTES_AGO->translate(),
                'count' => $minutes
            ];
        }

        $hours = (int) floor($minutes / 60);
        if ($hours < 24) {
            return [
                'unit' => ($hours === 1)
                    ? TimeAgoEnum::HOUR_AGO->translate()
                    : TimeAgoEnum::HOURS_AGO->translate(),
                'count' => $hours
            ];
        }

        $days = (int) floor($hours / 24);
        if ($days < 30) {
            return [
                'unit' => ($days === 1)
                    ? TimeAgoEnum::DAY_AGO->translate()
                    : TimeAgoEnum::DAYS_AGO->translate(),
                'count' => $days
            ];
        }

        $months = (int) floor($days / 30);
        if ($months < 12) {
            return [
                'unit' => ($months === 1)
                    ? TimeAgoEnum::MONTH_AGO->translate()
                    : TimeAgoEnum::MONTHS_AGO->translate(),
                'count' => $months
            ];
        }

        $years = (int) floor($days / 365);
        return [
            'unit' => ($years === 1)
                ? TimeAgoEnum::YEAR_AGO->translate()
                : TimeAgoEnum::YEARS_AGO->translate(),
            'count' => $years
        ];
    }
}