<?php
declare(strict_types=1);

namespace Neo\Core\Utils\Notification\Tests;

use Neo\Core\Utils\Notification\Enum\NotificationEnum;
use Neo\Core\Utils\Notification\NotificationCollector;
use PHPUnit\Framework\TestCase;

final class NotificationCollectorTest extends TestCase
{
    private NotificationCollector $collector;

    protected function setUp(): void
    {
        $this->collector = new NotificationCollector();
    }

    public function testGetNameReturnsMail(): void
    {
        self::assertSame('mail', $this->collector->getName());
    }

    public function testCollectReturnsZeroCountWhenEmpty(): void
    {
        $data = $this->collector->collect();

        self::assertSame(0, $data['count']);
        self::assertSame(0, $data['sent']);
        self::assertSame(0, $data['failed']);
        self::assertSame(0.0, $data['total_ms']);
    }

    public function testRecordSuccessIncrementsSent(): void
    {
        $this->collector->record('EmailChannel', 'welcome.twig', NotificationEnum::SUCCESS, 10.0);

        $data = $this->collector->collect();

        self::assertSame(1, $data['count']);
        self::assertSame(1, $data['sent']);
        self::assertSame(0, $data['failed']);
    }

    public function testRecordFailedIncrementsFailedCount(): void
    {
        $this->collector->record('EmailChannel', 'welcome.twig', NotificationEnum::FAILED, 5.0, 'SMTP error');

        $data = $this->collector->collect();

        self::assertSame(1, $data['failed']);
        self::assertSame('SMTP error', $data['mails'][0]['error']);
    }

    public function testTotalMsIsSumOfAllEntries(): void
    {
        $this->collector->record('EmailChannel', 'a.twig', NotificationEnum::SUCCESS, 10.0);
        $this->collector->record('SlackChannel', 'b.twig', NotificationEnum::SUCCESS, 20.0);

        $data = $this->collector->collect();

        self::assertSame(30.0, $data['total_ms']);
    }

    public function testMailsContainsAllEntries(): void
    {
        $this->collector->record('EmailChannel', 'welcome.twig', NotificationEnum::SUCCESS, 10.0);

        $data = $this->collector->collect();

        self::assertCount(1, $data['mails']);
        self::assertSame('sent', $data['mails'][0]['status']);
    }
}