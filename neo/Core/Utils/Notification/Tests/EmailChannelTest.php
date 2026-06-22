<?php
declare(strict_types=1);

namespace Neo\Core\Utils\Notification\Tests;

use Neo\Core\Utils\Notification\Channel\Email\EmailChannel;
use Neo\Core\Utils\Notification\Exception\ChannelException;
use PHPUnit\Framework\TestCase;

final class EmailChannelTest extends TestCase
{
    public function testRequiredApiKeyReturnsMailer(): void
    {
        self::assertSame('mailer', EmailChannel::requiredApiKey());
    }

    public function testSendThrowsWhenDisabled(): void
    {
        $channel = new EmailChannel()
            ->withApiConfig(['enabled' => false])
            ->setParams(['to' => 'user@example.com', 'subject' => 'Hi'])
            ->setBody('<p>Hello</p>');

        try {
            $channel->send();
            self::fail('Expected ChannelException.');
        } catch (ChannelException $e) {
            self::assertStringContainsString('disabled', $e->getMessage());
        }
    }

    public function testSendThrowsWhenNoRecipient(): void
    {
        $channel = new EmailChannel()
            ->withApiConfig(['enabled' => true])
            ->setParams(['subject' => 'Hi'])
            ->setBody('<p>Hello</p>');

        try {
            $channel->send();
            self::fail('Expected ChannelException.');
        } catch (ChannelException $e) {
            self::assertStringContainsString('to', $e->getMessage());
        }
    }

    public function testSendThrowsWhenNoSubject(): void
    {
        $channel = new EmailChannel()
            ->withApiConfig(['enabled' => true])
            ->setParams(['to' => 'user@example.com'])
            ->setBody('<p>Hello</p>');

        try {
            $channel->send();
            self::fail('Expected ChannelException.');
        } catch (ChannelException $e) {
            self::assertStringContainsString('subject', $e->getMessage());
        }
    }

    public function testSendThrowsWhenBodyEmpty(): void
    {
        $channel = new EmailChannel()
            ->withApiConfig(['enabled' => true])
            ->setParams(['to' => 'user@example.com', 'subject' => 'Hi'])
            ->setBody('');

        try {
            $channel->send();
            self::fail('Expected ChannelException.');
        } catch (ChannelException $e) {
            self::assertStringContainsString('body', strtolower($e->getMessage()));
        }
    }
}