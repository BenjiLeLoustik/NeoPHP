<?php
declare(strict_types=1);

namespace Neo\Core\Utils\Notification\Tests;

use Neo\Core\Utils\Notification\Channel\Sms\SmsChannel;
use Neo\Core\Utils\Notification\Exception\ChannelException;
use PHPUnit\Framework\TestCase;

final class SmsChannelTest extends TestCase
{
    public function testRequiredApiKeyReturnsSms(): void
    {
        self::assertSame('sms', SmsChannel::requiredApiKey());
    }

    public function testSendThrowsWhenChannelDisabled(): void
    {
        $channel = new SmsChannel()
            ->withApiConfig(['enabled' => false])
            ->setParams(['to' => '+33600000000'])
            ->setBody('Hello');

        try {
            $channel->send();
            self::fail('Expected ChannelException.');
        } catch (ChannelException $e) {
            self::assertStringContainsString('disabled', $e->getMessage());
        }
    }

    public function testSendThrowsWhenNoRecipient(): void
    {
        $channel = new SmsChannel()
            ->withApiConfig(['enabled' => true])
            ->setParams([])
            ->setBody('Hello');

        try {
            $channel->send();
            self::fail('Expected ChannelException.');
        } catch (ChannelException $e) {
            self::assertStringContainsString('to', $e->getMessage());
        }
    }

    public function testSendThrowsWhenBodyEmpty(): void
    {
        $channel = new SmsChannel()
            ->withApiConfig(['enabled' => true])
            ->setParams(['to' => '+33600000000'])
            ->setBody('');

        try {
            $channel->send();
            self::fail('Expected ChannelException.');
        } catch (ChannelException $e) {
            self::assertStringContainsString('body', strtolower($e->getMessage()));
        }
    }
}