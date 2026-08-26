<?php
declare(strict_types=1);

namespace Neo\Core\Http\Client\Flash;

use Neo\Core\DI\Container;
use Neo\Core\DI\Exception\ContainerException;
use Neo\Core\Error\Exception\FrameworkException;
use Neo\Core\Http\Client\Session\Session;
use Neo\Core\Utils\Config\ConfigManager;

class Flash
{
    private Session $session;

    /**
     * @var array{
     *     session_key: string,
     *     auto_expire: bool,
     *     types: array<int, string>
     * }
     */
    private array $config;

    private string $flashKey;

    /**
     * @throws ContainerException
     * @throws \ReflectionException
     */
    public function __construct(Container $container)
    {
        $configObj = $container->get(ConfigManager::class);
        $this->config = $configObj->from('session')->get('flash');

        $this->flashKey = $this->config['session_key'];

        $this->session = $container->get(Session::class);
        $this->initFlash();
    }

    private function initFlash(): void
    {
        if (!$this->session->has($this->flashKey)) {
            $this->session->set($this->flashKey, []);
        }
    }

    /**
     * @throws FrameworkException
     */
    public function add(string $type, string $message): void
    {
        if (!in_array($type, $this->config['types'], true)) {
            throw new FrameworkException(
                title: 'Flash Error',
                message: sprintf(
                    "Invalid Flash type : %s. Accepted types : %s",
                    $type,
                    implode(', ', $this->config['types'])
                ),
                code: 500
            );
        }

        $messages = $this->session->get($this->flashKey, []);
        $messages[] = [
            'type' => $type,
            'message' => $message
        ];

        $this->session->set($this->flashKey, $messages);
    }

    /**
     * @return array<int, array{type: string, message: string}>
     */
    public function getAll(): array
    {
        $messages = $this->session->get($this->flashKey, []);

        if ($this->config['auto_expire']) {
            $this->session->set($this->flashKey, []);
        }

        return $messages;
    }

    /**
     * @return array<int, string>
     */
    public function get(string $type): array
    {
        $all = $this->session->get($this->flashKey, []);
        $filtered = [];
        $remaining = [];

        foreach ($all as $item) {
            if ($item['type'] === $type) {
                $filtered[] = $item['message'];
            } else {
                $remaining[] = $item;
            }
        }

        if ($this->config['auto_expire']) {
            $this->session->set($this->flashKey, $remaining);
        }

        return $filtered;
    }

    /**
     * @return array<int, array{type: string, message: string}>
     */
    public function peek(): array
    {
        return $this->session->get($this->flashKey, []);
    }

    public function has(?string $type = null): bool
    {
        $messages = $this->session->get($this->flashKey, []);
        if (empty($messages)) {
            return false;
        }

        if ($type === null) {
            return true;
        }

        foreach ($messages as $item) {
            if ($item['type'] === $type) {
                return true;
            }
        }

        return false;
    }

    public function render(): string
    {
        $messages = $this->getAll();
        if (empty($messages)) {
            return '';
        }

        $html = [];
        foreach ($messages as $data) {
            $type = htmlspecialchars($data['type'], ENT_QUOTES, 'UTF-8');
            $msg = htmlspecialchars($data['message'], ENT_QUOTES, 'UTF-8');
            $html[] = "<div class='flash-message {$type}'>{$msg}</div>";
        }

        return implode("\n", $html);
    }
}