<?php
declare(strict_types=1);

namespace Neo\Core\Http\Client;

use Neo\Core\DI\Container;
use Neo\Core\Error\Exception\FrameworkException;
use Neo\Core\Utils\Config\Config;
use Neo\Core\View\View;
use Twig\Markup;

class Flash
{
    private Session $session;
    private array $config;
    private string $flashKey;

    public function __construct(Container $container)
    {
        $configObj = $container->get(Config::class);
        $this->config = $configObj->from('session')->get('flash');

        $this->flashKey = $this->config['session_key'];

        $this->session = $container->get(Session::class);
        $this->initFlash();

        $container->get(View::class)->registerTwigFunction(
            'flashes',
            function () {
                $html = '';
                foreach ($this->getAll() as $datas) {
                    $type = htmlspecialchars($datas['type'], ENT_QUOTES, 'UTF-8');
                    $msg  = htmlspecialchars($datas['message'], ENT_QUOTES, 'UTF-8');

                    $html .= "<span class='flash-message {$type}'>{$msg}</span>";
                }

                return new Markup($html, 'UTF-8');
            }
        );
    }

    private function initFlash(): void
    {
        if (!$this->session->has($this->flashKey)) {
            $this->session->set($this->flashKey, []);
        }
    }

    public function add(string $type, string $message): void
    {
        if (!in_array($type, $this->config['types'], true)) {
            throw new FrameworkException(
                title: 'Flash Error',
                message: "Type de flash invalide : '{$type}'. Types acceptés : " . implode(', ', $this->config['types']) . '.',
                code: 500
            );
        }

        $messages   = $this->session->get($this->flashKey, []);
        $messages[] = ['type' => $type, 'message' => $message];

        $this->session->set($this->flashKey, $messages);
    }

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
}