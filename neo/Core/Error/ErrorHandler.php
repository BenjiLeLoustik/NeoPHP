<?php
declare(strict_types=1);

namespace Neo\Core\Error;

use Neo\Core\DI\Container;
use Neo\Core\DI\Exception\ContainerException;
use Neo\Core\Error\Exception\FrameworkException;
use Neo\Core\Event\Event\ExceptionEvent;
use Neo\Core\Event\EventDispatcher;
use Neo\Core\Profiler\Profiler;
use Neo\Core\Profiler\Toolbar\Toolbar;
use Neo\Core\Utils\Config\Config;
use Neo\Core\Utils\Logger\Logger;
use Neo\Core\View\View;

class ErrorHandler
{
    private Container $container;
    private ?string $resolvedEnv = null;

    public function __construct(Container $container)
    {
        $this->container = $container;
    }

    public function setEnv(string $env): void
    {
        $this->resolvedEnv = $env;
    }

    private function getEnv(): string
    {
        if ($this->resolvedEnv !== null) {
            return $this->resolvedEnv;
        }

        try {
            $env = $this->container->get(Config::class)->from('app')->get('environment');
            $this->resolvedEnv = $env ?? 'prod';
        } catch (\Throwable) {
            $this->resolvedEnv = 'prod';
        }

        return $this->resolvedEnv;
    }

    public static function registerBootstrap(): void
    {
        set_exception_handler(function (\Throwable $e) {
            if (!headers_sent()) {
                http_response_code(500);
            }
            $exception = $e instanceof FrameworkException
                ? $e
                : FrameworkException::fromThrowable($e);
            echo self::renderFallbackHtml($exception, self::detectBootstrapEnv());
            exit;
        });

        set_error_handler(function (int $errno, string $errstr, string $errfile, int $errline): bool {
            if (!(error_reporting() & $errno)) {
                return true;
            }
            throw new \ErrorException($errstr, $errno, $errno, $errfile, $errline);
        });
    }

    private static function detectBootstrapEnv(): string
    {
        $host = $_SERVER['SERVER_NAME'] ?? $_SERVER['HTTP_HOST'] ?? '';
        return (str_contains($host, 'localhost') || str_contains($host, '127.0.0.1'))
            ? 'dev'
            : 'prod';
    }

    public function register(): void
    {
        set_exception_handler([$this, 'handleException']);
        set_error_handler([$this, 'handleError']);
    }

    /**
     * @throws ContainerException
     */
    public function handleException(\Throwable $e): never
    {
        $env = $this->getEnv();

        try {
            $logger = $this->container->get(Logger::class);
            $logger->channel('framework')->error(
                get_class($e) . ': ' . $e->getMessage(),
                ['trace' => $e->getTraceAsString()],
                $e->getFile() . ':' . $e->getLine()
            );
        } catch (\Throwable) {
            $logPath = $this->container->get('storagePath') . '/logs/framework.log';
            if (!is_dir(dirname($logPath))) {
                mkdir(dirname($logPath), 0777, true);
            }
            file_put_contents(
                $logPath,
                '[' . date('Y-m-d H:i:s') . '] ' . get_class($e) . ': ' . $e->getMessage()
                . ' in ' . $e->getFile() . ':' . $e->getLine()
                . "\n" . $e->getTraceAsString() . "\n\n",
                FILE_APPEND
            );
        }

        try {
            $dispatcher = $this->container->get(EventDispatcher::class);
            $exceptionEvent = new ExceptionEvent(
                $e instanceof FrameworkException ? $e : FrameworkException::fromThrowable($e)
            );
            $dispatcher->dispatch($exceptionEvent);
        } catch (\Throwable) {}

        $exception = $e instanceof FrameworkException ? $e : FrameworkException::fromThrowable($e);

        $code = $exception->getCode() ?: 500;
        $viewFile = $this->container->get('viewsPath') . "/errors/{$code}.html.twig";

        if (!headers_sent()) {
            http_response_code($code);
        }

        if (file_exists($viewFile) && $this->container->has(View::class)) {
            try {
                $view = $this->container->get(View::class);
                $html = $view->render("errors/{$code}.html.twig", [
                    'title'   => $exception->getTitle(),
                    'message' => $env === 'dev'
                        ? $exception->getMessage()
                        : 'An error occurred.',
                    'context' => $env === 'dev'
                        ? $exception->getContext()
                        : [],
                ]);
                echo $this->injectProfilerToolbar($html);
            } catch (\Throwable) {
                $this->renderFallback($exception);
            }
        } else {
            $this->renderFallback($exception);
        }

        exit;
    }

    /**
     * @throws ContainerException
     */
    public function handleError(int $errno, string $errstr, string $errfile, int $errline): bool
    {
        if (!(error_reporting() & $errno)) {
            return true;
        }

        $this->handleException(FrameworkException::fromThrowable(
            new \ErrorException($errstr, $errno, $errno, $errfile, $errline)
        ));
    }

    private function renderFallback(FrameworkException $exception): void
    {
        $env = $this->getEnv();
        echo $this->injectProfilerToolbar(self::renderFallbackHtml($exception, $env));
    }

    private static function renderFallbackHtml(FrameworkException $exception, string $env): string
    {
        $code = $exception->getCode() ?: 500;
        $isDev = $env === 'dev';

        $title = htmlspecialchars($exception->getTitle(), ENT_QUOTES, 'UTF-8');

        $message = htmlspecialchars(
            $isDev ? $exception->getMessage() : match(true) {
                $code === 404 => 'The page you are looking for could not be found.',
                $code === 403 => 'You do not have permission to access this resource.',
                $code === 401 => 'You must be authenticated to access this resource.',
                $code === 405 => 'The HTTP method used is not allowed for this route.',
                $code === 419 => 'Your session has expired, please refresh the page.',
                $code === 422 => 'The submitted data is invalid.',
                $code === 429 => 'Too many requests, please try again in a few moments.',
                $code >= 500 => 'An internal error has occurred, please try again later.',
                default => 'An unexpected error has occurred.',
            },
            ENT_QUOTES, 'UTF-8'
        );

        [$accent, $accentBg, $accentBorder] = match(true) {
            $code >= 500 => ['#c2410c', '#fff7ed', '#fed7aa'],
            $code === 404 => ['#1d4ed8', '#eff6ff', '#bfdbfe'],
            $code === 403, $code === 401 => ['#7e22ce', '#faf5ff', '#e9d5ff'],
            default => ['#b91c1c', '#fef2f2', '#fecaca'],
        };

        $devBlock = '';
        if ($isDev) {
            $file = htmlspecialchars($exception->getFile(), ENT_QUOTES, 'UTF-8');
            $line = $exception->getLine();
            $class = htmlspecialchars(get_class($exception), ENT_QUOTES, 'UTF-8');

            $traceRows = '';
            $i = 0;
            foreach (explode("\n", $exception->getTraceAsString()) as $traceLine) {
                if (trim($traceLine) === '') {
                    continue;
                }
                $traceLine = htmlspecialchars($traceLine, ENT_QUOTES, 'UTF-8');
                $rowBg = $i % 2 === 0 ? '#ffffff' : '#f9fafb';
                if (preg_match('/^(#\d+)\s+(.+)$/', $traceLine, $m)) {
                    $num  = $m[1];
                    $rest = preg_replace(
                        '/^(.+\(\d+\)): (.+)$/',
                        '<span style="color:#6b7280;">$1</span>'
                        . '<span style="color:#d1d5db;">:</span> '
                        . '<span style="color:#111827;font-weight:500;">$2</span>',
                        $m[2]
                    );
                    $traceRows .= <<<HTML
                <tr style="background:{$rowBg};border-bottom:1px solid #f3f4f6;">
                    <td style="padding:0.5rem 1rem;font-family:monospace;font-size:0.72rem;color:#d1d5db;vertical-align:top;white-space:nowrap;user-select:none;border-right:1px solid #f3f4f6;min-width:48px;text-align:right;">{$num}</td>
                    <td style="padding:0.5rem 1.25rem;font-family:monospace;font-size:0.72rem;line-height:1.7;word-break:break-all;">{$rest}</td>
                </tr>
                HTML;
                } else {
                    $traceRows .= <<<HTML
                <tr style="background:{$rowBg};border-bottom:1px solid #f3f4f6;">
                    <td colspan="2" style="padding:0.5rem 1.25rem;font-family:monospace;font-size:0.72rem;color:#9ca3af;">{$traceLine}</td>
                </tr>
                HTML;
                }
                $i++;
            }

            $contextBlock = '';
            if (!empty($exception->getContext())) {
                $context = htmlspecialchars(print_r($exception->getContext(), true), ENT_QUOTES, 'UTF-8');
                $contextBlock = <<<HTML
            <div style="border-top:1px solid #e5e7eb;">
                <div style="padding:0.6rem 1.25rem;background:#f9fafb;border-bottom:1px solid #e5e7eb;">
                    <span style="font-size:0.65rem;font-weight:700;letter-spacing:0.1em;text-transform:uppercase;color:#9ca3af;">Contexte</span>
                </div>
                <pre style="margin:0;padding:1.25rem;font-family:monospace;font-size:0.72rem;line-height:1.7;color:#374151;background:#f9fafb;overflow-x:auto;white-space:pre;">{$context}</pre>
            </div>
            HTML;
            }

            $devBlock = <<<HTML
        <div style="margin-top:2rem;border-radius:8px;overflow:hidden;border:1px solid #e5e7eb;box-shadow:0 1px 3px rgba(0,0,0,0.06);">

            <div style="background:#f9fafb;padding:0.9rem 1.25rem;border-bottom:1px solid #e5e7eb;display:flex;align-items:center;gap:0.75rem;flex-wrap:wrap;">
                <span style="font-size:0.65rem;font-weight:700;letter-spacing:0.1em;text-transform:uppercase;color:#fff;background:{$accent};padding:0.2rem 0.65rem;border-radius:4px;">Exception</span>
                <code style="font-family:monospace;font-size:0.8rem;color:{$accent};word-break:break-all;">{$class}</code>
            </div>

            <div style="background:#fff;padding:0.75rem 1.25rem;border-bottom:1px solid #e5e7eb;display:flex;align-items:center;gap:1rem;flex-wrap:wrap;">
                <span style="font-family:monospace;font-size:0.72rem;color:#6b7280;word-break:break-all;flex:1;">{$file}</span>
                <span style="font-family:monospace;font-size:0.72rem;font-weight:600;color:{$accent};background:{$accentBg};border:1px solid {$accentBorder};padding:0.15rem 0.6rem;border-radius:4px;white-space:nowrap;flex-shrink:0;">ligne {$line}</span>
            </div>

            <div style="background:#f9fafb;padding:0.6rem 1.25rem;border-bottom:1px solid #e5e7eb;">
                <span style="font-size:0.65rem;font-weight:700;letter-spacing:0.1em;text-transform:uppercase;color:#9ca3af;">Stack trace</span>
            </div>
            <table style="width:100%;border-collapse:collapse;">
                {$traceRows}
            </table>

            {$contextBlock}

        </div>
        HTML;
        }

        return <<<HTML
    <!DOCTYPE html>
    <html lang="fr">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>{$code} — {$title}</title>
    </head>
    <body style="margin:0;font-family:system-ui,-apple-system,sans-serif;background:#f9fafb;color:#111827;min-height:100vh;">

        <div style="border-top:3px solid {$accent};background:#fff;border-bottom:1px solid #e5e7eb;padding:0.6rem 2rem;display:flex;align-items:center;justify-content:space-between;">
            <span style="font-size:0.75rem;font-weight:600;color:#9ca3af;letter-spacing:0.08em;text-transform:uppercase;">NeoPHP</span>
            <span style="font-size:0.72rem;color:#d1d5db;font-family:monospace;">{$env}</span>
        </div>

        <div style="max-width:900px;margin:0 auto;padding:3.5rem 2rem 5rem;">

            <div style="display:flex;align-items:center;gap:1.5rem;margin-bottom:2rem;padding-bottom:2rem;border-bottom:1px solid #e5e7eb;">
                <div style="font-family:monospace;font-size:4rem;font-weight:800;color:{$accent};line-height:1;letter-spacing:-0.03em;flex-shrink:0;">{$code}</div>
                <div>
                    <div style="font-size:1.4rem;font-weight:700;color:#111827;margin-bottom:0.4rem;letter-spacing:-0.02em;">{$title}</div>
                    <div style="font-size:0.9rem;color:#6b7280;line-height:1.6;">{$message}</div>
                </div>
            </div>

            {$devBlock}

        </div>

    </body>
    </html>
    HTML;
    }

    private function injectProfilerToolbar(string $html): string
    {
        if (!defined('NEO_PROFILER_ENABLED') || !NEO_PROFILER_ENABLED) {
            return $html;
        }

        try {
            $toolbar = new Toolbar(
                Profiler::getInstance()
            );
            $rendered = $toolbar->render();

            if (str_contains($html, '</body>')) {
                return str_replace('</body>', $rendered . '</body>', $html);
            }

            return $html . $rendered;
        } catch (\Throwable) {
            return $html;
        }
    }
}