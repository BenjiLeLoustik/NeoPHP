<?php
declare(strict_types=1);

namespace Neo\Core\Utils\Notification\Tests;

use Neo\Core\Utils\Notification\Channel\Slack\SlackChannel;
use Neo\Core\Utils\Notification\Exception\ChannelException;
use PHPUnit\Framework\TestCase;

final class SlackChannelTest extends TestCase
{
    public function testRequiredApiKeyReturnsSlack(): void
    {
        self::assertSame('slack', SlackChannel::requiredApiKey());
    }

    public function testSendThrowsWhenDisabled(): void
    {
        $channel = new SlackChannel()
            ->withApiConfig(['enabled' => false, 'webhook_url' => 'https://hooks.slack.com/test'])
            ->setBody('Hello');

        try {
            $channel->send();
            self::fail('Expected ChannelException.');
        } catch (ChannelException $e) {
            self::assertStringContainsString('disabled', $e->getMessage());
        }
    }

    public function testSendThrowsWhenWebhookUrlMissing(): void
    {
        $channel = new SlackChannel()
            ->withApiConfig(['enabled' => true])
            ->setBody('Hello');

        try {
            $channel->send();
            self::fail('Expected ChannelException.');
        } catch (ChannelException $e) {
            self::assertStringContainsString('webhook_url', $e->getMessage());
        }
    }

    public function testSendThrowsWhenBodyEmpty(): void
    {
        $channel = new SlackChannel()
            ->withApiConfig(['enabled' => true, 'webhook_url' => 'https://hooks.slack.com/test'])
            ->setBody('');

        try {
            $channel->send();
            self::fail('Expected ChannelException.');
        } catch (ChannelException $e) {
            self::assertStringContainsString('body', strtolower($e->getMessage()));
        }
    }
}