<?php
declare(strict_types=1);

namespace Neo\Core\Utils\Notification\Tests;

use Neo\Core\DI\Container;
use Neo\Core\DI\Exception\ContainerException;
use Neo\Core\Utils\Notification\Channel\ChannelInterface;
use Neo\Core\Utils\Notification\Exception\ChannelException;
use Neo\Core\Utils\Notification\Exception\NotificationException;
use Neo\Core\Utils\Notification\NotificationManager;
use Neo\Core\Utils\Notification\Tests\Fixture\NoApiKeyChannel;
use PHPUnit\Framework\TestCase;

final class NotificationManagerTest extends TestCase
{
    private NotificationManager $manager;

    protected function setUp(): void
    {
        $container = $this->createMock(Container::class);
        $this->manager = new NotificationManager($container);
    }

    public function testDoSendThrowsWhenNoChannelSelected(): void
    {
        try {
            $this->manager->doSend();
            self::fail('Expected NotificationException.');
        } catch (NotificationException $e) {
            self::assertStringContainsString('channel()', $e->getMessage());
        } catch (ContainerException|ChannelException $e) {
        }
    }

    public function testChannelThrowsForNonExistentClass(): void
    {
        try {
            $this->manager->channel($this->castToChannelClass('App\Invalid\MissingChannel'));
            self::fail('Expected NotificationException.');
        } catch (NotificationException $e) {
            self::assertStringContainsString('does not exist', $e->getMessage());
        } catch (ContainerException $e) {
        }
    }

    public function testChannelThrowsForClassNotImplementingInterface(): void
    {
        try {
            $this->manager->channel($this->castToChannelClass(\stdClass::class));
            self::fail('Expected NotificationException.');
        } catch (NotificationException $e) {
            self::assertStringContainsString('ChannelInterface', $e->getMessage());
        } catch (ContainerException $e) {
        }
    }

    /**
     * @param string $className
     * @return class-string<ChannelInterface>
     */
    private function castToChannelClass(string $className): string
    {
        /** @var class-string<ChannelInterface> */
        return $className;
    }

    /**
     * @throws NotificationException
     * @throws ContainerException
     */
    public function testChannelAcceptsValidChannelWithNoApiKey(): void
    {
        $result = $this->manager->channel(NoApiKeyChannel::class);

        self::assertSame($this->manager, $result);
    }

    public function testSetParamsReturnsSameInstance(): void
    {
        self::assertSame($this->manager, $this->manager->setParams(['to' => 'test@test.com']));
    }

    public function testSetTemplateReturnsSameInstance(): void
    {
        self::assertSame($this->manager, $this->manager->setTemplate('email.twig'));
    }
}