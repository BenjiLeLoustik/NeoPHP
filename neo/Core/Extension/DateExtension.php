<?php
declare(strict_types=1);

namespace Neo\Core\Extension;

use DateTime;
use DateTimeImmutable;
use DateTimeInterface;
use DateInterval;
use DateTimeZone;

class DateExtension
{

    public function now(string $timezone = 'UTC'): DateTimeImmutable
    {
        return new DateTimeImmutable('now', new DateTimeZone($timezone));
    }

    public function parse(string $date, string $format = 'Y-m-d H:i:s'): DateTimeImmutable|false
    {
        $dt = DateTimeImmutable::createFromFormat($format, $date);
        return $dt ?: false;
    }

    public function fromTimestamp(int $timestamp): DateTimeImmutable
    {
        return (new DateTimeImmutable())->setTimestamp($timestamp);
    }

    public function format(DateTimeInterface|string $date, string $format = 'd/m/Y'): string
    {
        $dt = $date instanceof DateTimeInterface ? $date : new DateTimeImmutable($date);
        return $dt->format($format);
    }

    public function toTimestamp(DateTimeInterface|string $date): int
    {
        $dt = $date instanceof DateTimeInterface ? $date : new DateTimeImmutable($date);
        return $dt->getTimestamp();
    }

    public function humanDiff(DateTimeInterface|string $date, ?DateTimeInterface $reference = null): string
    {
        $dt  = $date instanceof DateTimeInterface ? $date : new DateTimeImmutable($date);
        $ref = $reference ?? new DateTimeImmutable();
        $diff = $ref->diff($dt);

        return match (true) {
            $diff->y > 0 => $diff->y . ' year' . ($diff->y > 1 ? 's' : '') . ' ago',
            $diff->m > 0 => $diff->m . ' month' . ($diff->m > 1 ? 's' : '') . ' ago',
            $diff->d >= 7 => floor($diff->d / 7) . ' week' . (floor($diff->d / 7) > 1 ? 's' : '') . ' ago',
            $diff->d > 0 => $diff->d . ' day' . ($diff->d > 1 ? 's' : '') . ' ago',
            $diff->h > 0 => $diff->h . ' hour' . ($diff->h > 1 ? 's' : '') . ' ago',
            $diff->i > 0 => $diff->i . ' minute' . ($diff->i > 1 ? 's' : '') . ' ago',
            default => 'just now',
        };
    }

    public function diffInDays(DateTimeInterface|string $from, DateTimeInterface|string $to): int
    {
        $from = $from instanceof DateTimeInterface ? $from : new DateTimeImmutable($from);
        $to = $to instanceof DateTimeInterface ? $to : new DateTimeImmutable($to);
        return (int) $from->diff($to)->days;
    }

    public function diffInHours(DateTimeInterface|string $from, DateTimeInterface|string $to): int
    {
        $from = $from instanceof DateTimeInterface ? $from : new DateTimeImmutable($from);
        $to = $to instanceof DateTimeInterface ? $to : new DateTimeImmutable($to);
        return (int) (($to->getTimestamp() - $from->getTimestamp()) / 3600);
    }

    public function diffInMinutes(DateTimeInterface|string $from, DateTimeInterface|string $to): int
    {
        $from = $from instanceof DateTimeInterface ? $from : new DateTimeImmutable($from);
        $to = $to instanceof DateTimeInterface ? $to : new DateTimeImmutable($to);
        return (int) (($to->getTimestamp() - $from->getTimestamp()) / 60);
    }

    public function addDays(DateTimeInterface|string $date, int $days): DateTimeImmutable
    {
        $dt = $date instanceof DateTimeInterface
            ? DateTimeImmutable::createFromInterface($date)
            : new DateTimeImmutable($date);
        return $dt->modify("+{$days} days");
    }

    public function subDays(DateTimeInterface|string $date, int $days): DateTimeImmutable
    {
        $dt = $date instanceof DateTimeInterface
            ? DateTimeImmutable::createFromInterface($date)
            : new DateTimeImmutable($date);
        return $dt->modify("-{$days} days");
    }

    public function addMonths(DateTimeInterface|string $date, int $months): DateTimeImmutable
    {
        $dt = $date instanceof DateTimeInterface
            ? DateTimeImmutable::createFromInterface($date)
            : new DateTimeImmutable($date);
        return $dt->modify("+{$months} months");
    }

    public function addYears(DateTimeInterface|string $date, int $years): DateTimeImmutable
    {
        $dt = $date instanceof DateTimeInterface
            ? DateTimeImmutable::createFromInterface($date)
            : new DateTimeImmutable($date);
        return $dt->modify("+{$years} years");
    }

    public function isPast(DateTimeInterface|string $date): bool
    {
        $dt = $date instanceof DateTimeInterface
            ? $date
            : new DateTimeImmutable($date);
        return $dt < new DateTimeImmutable();
    }

    public function isFuture(DateTimeInterface|string $date): bool
    {
        $dt = $date instanceof DateTimeInterface
            ? $date
            : new DateTimeImmutable($date);
        return $dt > new DateTimeImmutable();
    }

    public function isToday(DateTimeInterface|string $date): bool
    {
        $dt = $date instanceof DateTimeInterface
            ? $date
            : new DateTimeImmutable($date);
        return $dt->format('Y-m-d') === (new DateTimeImmutable())->format('Y-m-d');
    }

    public function isWeekend(DateTimeInterface|string $date): bool
    {
        $dt = $date instanceof DateTimeInterface
            ? $date
            : new DateTimeImmutable($date);
        return in_array((int) $dt->format('N'), [6, 7]);
    }

    public function isLeapYear(DateTimeInterface|string|int $date): bool
    {
        $year = is_int($date)
            ? $date
            : (($date instanceof DateTimeInterface ? $date : new DateTimeImmutable($date))->format('Y'));
        return (int) date('L', mktime(0, 0, 0, 1, 1, (int) $year)) === 1;
    }

    public function isBetween(DateTimeInterface|string $date, DateTimeInterface|string $from, DateTimeInterface|string $to): bool
    {
        $dt = $date instanceof DateTimeInterface ? $date : new DateTimeImmutable($date);
        $from = $from instanceof DateTimeInterface ? $from : new DateTimeImmutable($from);
        $to = $to instanceof DateTimeInterface ? $to : new DateTimeImmutable($to);
        return $dt >= $from && $dt <= $to;
    }

    public function addBusinessDays(DateTimeInterface|string $date, int $days): DateTimeImmutable
    {
        $dt = $date instanceof DateTimeInterface ? DateTimeImmutable::createFromInterface($date) : new DateTimeImmutable($date);
        $added = 0;

        while ($added < $days) {
            $dt = $dt->modify('+1 day');
            if (!$this->isWeekend($dt)) {
                $added++;
            }
        }

        return $dt;
    }

    public function countBusinessDays(DateTimeInterface|string $from, DateTimeInterface|string $to): int
    {
        $from = $from instanceof DateTimeInterface
            ? DateTimeImmutable::createFromInterface($from)
            : new DateTimeImmutable($from);
        $to = $to instanceof DateTimeInterface
            ? DateTimeImmutable::createFromInterface($to)
            : new DateTimeImmutable($to);
        $count = 0;
        $current = $from;

        while ($current <= $to) {
            if (!$this->isWeekend($current)) {
                $count++;
            }
            $current = $current->modify('+1 day');
        }

        return $count;
    }

    public function age(DateTimeInterface|string $birthdate): int
    {
        $dt = $birthdate instanceof DateTimeInterface
            ? $birthdate
            : new DateTimeImmutable($birthdate);
        return (int) $dt->diff(new DateTimeImmutable())->y;
    }
}