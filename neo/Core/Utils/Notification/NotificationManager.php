<?php
declare(strict_types=1);

namespace Neo\Core\Utils\Notification;

use Neo\Core\DI\Container;
use Neo\Core\DI\Exception\ContainerException;
use Neo\Core\Profiler\Profiler;
use Neo\Core\Utils\Config\Config;
use Neo\Core\Utils\Config\Exception\ConfigException;
use Neo\Core\Utils\Notification\Channel\ChannelInterface;
use Neo\Core\Utils\Notification\Enum\NotificationEnum;
use Neo\Core\Utils\Notification\Exception\ChannelException;
use Neo\Core\Utils\Notification\Exception\NotificationException;
use Neo\Core\View\ViewManager;

/**
 * Notification builder.
 *
 * Usage :
 *   $this->notification()
 *        ->channel(EmailChannel::class)
 *        ->setParams([...])
 *        ->setTemplate('emails/welcome.html.twig', ['user' => $user])
 *        ->doSend();  // NotificationEnum
 */
class NotificationManager
{
    private ChannelInterface $channel;

    /** @var class-string<ChannelInterface> */
    private string $channelClass;

    /** @var array<string, mixed> */
    private array $params = [];

    private string $template    = '';

    /** @var array<string, mixed> */
    private array $templateVars = [];

    public function __construct(
        private readonly Container $container,
    ) {}

    /**
     * @param class-string<ChannelInterface> $channelClass
     * @throws NotificationException
     * @throws ContainerException
     */
    public function channel(string $channelClass, ?string $apiKeyOverride = null): static
    {
        if (!class_exists($channelClass)) {
            throw new NotificationException(
                title: 'Unknown Channel',
                message: sprintf("Channel class '%s' does not exist.", $channelClass),
                code: 500,
            );
        }

        if (!is_subclass_of($channelClass, ChannelInterface::class)) {
            throw new NotificationException(
                title: 'Invalid Channel',
                message: sprintf("'%s' must implement ChannelInterface.", $channelClass),
                code: 500,
            );
        }

        /** @var ChannelInterface $instance */
        $instance = new $channelClass();

        $requiredKey = $apiKeyOverride ?? $channelClass::requiredApiKey();
        if ($requiredKey !== null) {
            $instance->withApiConfig($this->resolveApiConfig($requiredKey, $channelClass));
        }

        $this->channel = $instance;
        $this->channelClass = $channelClass;

        return $this;
    }

    /**
     * @param array<string, mixed> $params
     */
    public function setParams(array $params): static
    {
        $this->params = $params;
        return $this;
    }

    /**
     * @param array<string, mixed> $vars
     */
    public function setTemplate(string $template, array $vars = []): static
    {
        $this->template = $template;
        $this->templateVars = $vars;
        return $this;
    }

    /**
     * @throws NotificationException
     * @throws ChannelException
     * @throws ContainerException
     */
    public function doSend(): NotificationEnum
    {
        $this->assertChannelSelected();

        $body = $this->renderTemplate();

        $this->channel
            ->setParams($this->params)
            ->setBody($body);

        $start = microtime(true);
        $result = NotificationEnum::FAILED;
        $error = null;

        try {
            $result = $this->channel->send();
        } catch (ChannelException $e) {
            $error = $e->getMessage();
            throw $e;
        } finally {
            $this->recordToProfiler(
                channelClass: $this->channelClass,
                template: $this->template,
                status: $result,
                durationMs: (microtime(true) - $start) * 1000,
                error: $error,
            );
            $this->reset();
        }

        return $result;
    }

    /**
     * @return array<string, mixed>
     * @throws NotificationException
     * @throws ContainerException
     */
    private function resolveApiConfig(string $keyPath, string $channelClass): array
    {
        /** @var Config $config */
        $config = $this->container->get(Config::class);

        try {
            $apiConfig = $config->from('api')->get($keyPath);
        } catch (ConfigException $e) {
            throw new NotificationException(
                title: 'Missing API Config File',
                message: sprintf(
                    "Channel '%s' requires 'api.config.php' with key '%s', but the config file was not found. "
                    . "Please create neo/config/api.config.php and add the '%s' key.",
                    $channelClass, $keyPath, $keyPath,
                ),
                code: 500,
                context: ['channel' => $channelClass, 'key' => $keyPath],
                previous: $e,
            );
        }

        if (!is_array($apiConfig) || empty($apiConfig)) {
            throw new NotificationException(
                title: 'Missing API Config Key',
                message: sprintf(
                    "Channel '%s' requires key '%s' in api.config.php, but it is absent or empty. "
                    . "Please add the '%s' configuration block.",
                    $channelClass, $keyPath, $keyPath,
                ),
                code: 500,
                context: ['channel' => $channelClass, 'key' => $keyPath],
            );
        }

        return $apiConfig;
    }

    /**
     * @throws NotificationException
     * @throws ContainerException
     */
    private function renderTemplate(): string
    {
        if ($this->template === '') {
            return '';
        }

        /** @var ViewManager $view */
        $view = $this->container->get(ViewManager::class);

        try {
            return $view->render($this->template, $this->templateVars);
        } catch (\Throwable $e) {
            throw new NotificationException(
                title: 'Template Render Error',
                message: sprintf("Failed to render notification template '%s': %s", $this->template, $e->getMessage()),
                code: 500,
                context: ['template' => $this->template],
                previous: $e,
            );
        }
    }

    /**
     * @param class-string<ChannelInterface> $channelClass
     */
    private function recordToProfiler(
        string $channelClass,
        string $template,
        NotificationEnum $status,
        float $durationMs,
        ?string $error,
    ): void {
        if (!defined('NEO_PROFILER_ENABLED') || !NEO_PROFILER_ENABLED) {
            return;
        }

        $collector = Profiler::getInstance()->getCollector('mail');
        if (!$collector instanceof NotificationCollector) {
            return;
        }

        $collector->record($channelClass, $template, $status, $durationMs, $error);
    }

    /**
     * @throws NotificationException
     */
    private function assertChannelSelected(): void
    {
        if (!isset($this->channel)) {
            throw new NotificationException(
                title: 'No Channel Selected',
                message: "Call channel() before doSend().",
                code: 500,
            );
        }
    }

    private function reset(): void
    {
        unset($this->channel, $this->channelClass);
        $this->params = [];
        $this->template = '';
        $this->templateVars = [];
    }
}