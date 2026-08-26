<?php
declare(strict_types=1);

namespace Neo\Core\Tools\Helper\Date\Enum;

enum TimeAgoEnum: string
{
    case JUST_NOW = 'just_now';
    case SECONDS_AGO = 'seconds_ago';
    case MINUTE_AGO = 'minute_ago';
    case MINUTES_AGO = 'minutes_ago';
    case HOUR_AGO = 'hour_ago';
    case HOURS_AGO = 'hours_ago';
    case DAY_AGO = 'day_ago';
    case DAYS_AGO = 'days_ago';
    case MONTH_AGO = 'month_ago';
    case MONTHS_AGO = 'months_ago';
    case YEAR_AGO = 'year_ago';
    case YEARS_AGO = 'years_ago';

    public function translate(): string
    {
        return match($this) {
            self::JUST_NOW => 'just now',
            self::SECONDS_AGO => ':count seconds ago',
            self::MINUTE_AGO => ':count minute ago',
            self::MINUTES_AGO => ':count minutes ago',
            self::HOUR_AGO => ':count hour ago',
            self::HOURS_AGO => ':count hours ago',
            self::DAY_AGO => ':count day ago',
            self::DAYS_AGO => ':count days ago',
            self::MONTH_AGO => ':count month ago',
            self::MONTHS_AGO => ':count months ago',
            self::YEAR_AGO => ':count year ago',
            self::YEARS_AGO => ':count years ago'
        };
    }
}