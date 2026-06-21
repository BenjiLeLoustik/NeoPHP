<?php
declare(strict_types=1);

namespace Neo\Core\Cron\Tests\Fixture;

use Neo\Core\Cron\Attribute\Cron;

class ConcreteCronClass
{
    /**
     * @var list<string>
     */
    public array $calls = [];

    #[Cron(expression: '* * * * *', description: 'Runs every minute')]
    public function everyMinute(): void
    {
        $this->calls[] = 'everyMinute';
    }

    #[Cron(expression: '0 * * * *', description: 'Runs every hour', timezone: 'Europe/Paris', lock: true)]
    public function everyHour(): void
    {
        $this->calls[] = 'everyHour';
    }

    public function notACronMethod(): void
    {
        $this->calls[] = 'notACronMethod';
    }
}