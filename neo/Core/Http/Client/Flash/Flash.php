<?php
declare(strict_types=1);

namespace Neo\Core\Http\Client\Flash;

use Neo\Core\DI\Container;
use Neo\Core\DI\Exception\ContainerException;
use Neo\Core\Error\Exception\FrameworkException;
use Neo\Core\Http\Client\Session\Session;
use Neo\Core\Utils\Config\Config;

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
     */
    public function __construct(Container $container)
    {
        $configObj = $container->get(Config::class);
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
                message: "Type de flash invalide : '{$type}'. Types acceptés : " . implode(', ', $this->config['types']) . '.',
                code: 500
            );
        }

        $messages = $this->session->get($this->flashKey, []);
        $messages[] = ['type' => $type, 'message' => $message];

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

    public function has(): bool
    {
        return !empty($this->session->get($this->flashKey, []));
    }

    public function render(): string
    {
        $html = '';
        foreach ($this->getAll() as $data) {
            $type = htmlspecialchars($data['type'], ENT_QUOTES, 'UTF-8');
            $msg = htmlspecialchars($data['message'], ENT_QUOTES, 'UTF-8');
            $html .= "<span class='flash-message {$type}'>{$msg}</span>";
        }
        return $html;
    }
}