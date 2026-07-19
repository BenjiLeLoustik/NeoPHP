<?php
declare(strict_types=1);

namespace Neo\Core\Event;

use Neo\Core\DI\Container;
use Neo\Core\DI\Exception\ContainerException;
use Neo\Core\Event\Attribute\AsListener;
use Neo\Core\Event\Exception\EventException;
use Neo\Core\Event\Interface\EventInterface;
use Neo\Core\Event\Interface\EventSubscriberInterface;
use Neo\Core\Profiler\Profiler;
use Neo\Core\Utils\Config\Config;
use Neo\Core\Utils\Scanner\Attribute\ScannerAttribute;

class EventManager
{
    /**
     * @var array<class-string, list<array{
     * class: class-string,
     * priority: int,
     * method?: string|array{0: string, 1?: int},
     * instance?: object
     * }>>
     */
    private array $listeners = [];
    private Container $container;

    public function __construct(Container $container)
    {
        $this->container = $container;
        $this->scanListeners();
    }

    private function isDebug(): bool
    {
        try {
            return $this->container->get(Config::class)->from('app')->get('environment') === 'dev';
        } catch (\Throwable) {
            return false;
        }
    }

    /**
     * @throws EventException
     * @throws ContainerException
     * @throws \ReflectionException
     */
    private function scanListeners(): void
    {
        $cacheFile = $this->container->get('storagePath') . '/var/cache/events/listeners.php';

        if (!$this->isDebug() && file_exists($cacheFile)) {
            $this->listeners = unserialize(file_get_contents($cacheFile));
            return;
        }

        $listenersPath = $this->container->get('listenersPath');

        if (!is_dir($listenersPath)) {
            return;
        }

        $rii = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($listenersPath)
        );

        foreach ($rii as $file) {
            if (!$file->isFile() || $file->getExtension() !== 'php') {
                continue;
            }

            $filePath = $file->getRealPath();
            $src = file_get_contents($filePath);
            if ($src === false) continue;

            $namespace = '';
            if (preg_match('/namespace\s+([^;]+);/i', $src, $m)) {
                $namespace = trim($m[1]);
            }

            if (!preg_match('/class\s+([A-Za-z0-9_]+)/i', $src, $mClass)) {
                continue;
            }

            $fqcn = $namespace !== '' ? $namespace . '\\' . $mClass[1] : $mClass[1];

            require_once $filePath;

            if (!class_exists($fqcn)) continue;

            $results = new ScannerAttribute($fqcn)
                ->onClass()
                ->withAttribute(AsListener::class)
                ->scan();

            foreach ($results as $entry) {
                /** @var AsListener $listener */
                $listener = $entry['attribute'];
                $this->listeners[$listener->event][] = [
                    'class' => $fqcn,
                    'priority' => $listener->priority,
                ];
            }

            if (new \ReflectionClass($fqcn)->implementsInterface(EventSubscriberInterface::class)) {
                foreach ($fqcn::getSubscribedEvents() as $eventClass => $method) {
                    $this->listeners[$eventClass][] = [
                        'class' => $fqcn,
                        'method' => $method,
                        'priority' => 0,
                    ];
                }
            }
        }

        foreach ($this->listeners as &$list) {
            usort($list, fn($a, $b) => $b['priority'] <=> $a['priority']);
        }

        if (!$this->isDebug()) {
            $cacheDir = dirname($cacheFile);
            if (!is_dir($cacheDir) && !mkdir($cacheDir, 0777, true) && !is_dir($cacheDir)) {
                throw new EventException(
                    title: 'Event CacheManager Directory Error',
                    message: sprintf("Unable to create event cache directory '%s'.", $cacheDir),
                    code: 500
                );
            }
            if (file_put_contents($cacheFile, serialize($this->listeners)) === false) {
                throw new EventException(
                    title: 'Event CacheManager Write Error',
                    message: sprintf("Unable to write event cache file '%s'.", $cacheFile),
                    code: 500
                );
            }
        }
    }

    /**
     * @throws EventException
     * @throws ContainerException
     */
    public function dispatch(EventInterface $event): EventInterface
    {
        $eventClass = get_class($event);
        $listeners = $this->listeners[$eventClass] ?? [];
        $called = [];

        $t0 = microtime(true);

        foreach ($listeners as $meta) {
            if ($event->isPropagationStopped()) {
                break;
            }

            $listener = $meta['instance'] ?? $this->container->get($meta['class']);

            $method = $meta['method'] ?? 'handle';

            if (!method_exists($listener, $method)) {
                throw new EventException(
                    title: 'Event Listener Error',
                    message: sprintf("Method '%s' does not exist on listener '%s'.", $method, $meta['class']),
                    code: 500
                );
            }

            $listener->$method($event);
            $called[] = $meta['class'];
        }

        if (defined('NEO_PROFILER_ENABLED') && NEO_PROFILER_ENABLED) {
            $ms = (microtime(true) - $t0) * 1000;
            $ec = Profiler::getInstance()->getCollector('events');
            $ec?->record($eventClass, $called, $ms);
        }

        return $event;
    }

    public function addListener(
        string $eventClass,
        string $listenerClass,
        int $priority = 0,
        string $method = 'handle'
    ): void {
        $this->listeners[$eventClass][] = array(
            'class' => $listenerClass,
            'method' => $method,
            'priority' => $priority,
        );

        usort($this->listeners[$eventClass], fn($a, $b) => $b['priority'] <=> $a['priority']);
    }

    public function addListenerInstance(string $eventClass, object $instance, string $method = 'handle', int $priority = 0): void
    {
        $this->listeners[$eventClass][] = [
            'instance' => $instance,
            'class' => get_class($instance),
            'method' => $method,
            'priority' => $priority,
        ];

        usort(
            $this->listeners[$eventClass],
            fn ($a, $b) => $b['priority'] <=> $a['priority'],
        );
    }

    public function addSubscriber(EventSubscriberInterface $subscriber): void
    {
        foreach ($subscriber::getSubscribedEvents() as $eventClass => $method) {
            $this->addListener($eventClass, get_class($subscriber), 0, $method);
        }
    }

    /**
     * @return ($eventClass is null
     * ? array<class-string, list<array{class: class-string, priority: int, method?: string|array{0: string, 1?: int}, instance?: object}>>
     * : list<array{class: class-string, priority: int, method?: string|array{0: string, 1?: int}, instance?: object}>)
     */
    public function getListeners(?string $eventClass = null): array
    {
        if ($eventClass !== null) {
            return $this->listeners[$eventClass] ?? [];
        }
        return $this->listeners;
    }
}