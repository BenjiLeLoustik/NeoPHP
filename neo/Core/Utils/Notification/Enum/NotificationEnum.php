<?php
declare(strict_types=1);

namespace Neo\Core\Utils\Notification\Enum;

enum NotificationEnum: string
{
    case SUCCESS = 'success';
    case FAILED  = 'failed';
    case PARTIAL = 'partial';
    case SKIPPED = 'skipped';
}