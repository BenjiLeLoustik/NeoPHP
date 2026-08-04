<?php
declare(strict_types=1);

namespace Neo\Core\Event;

use Neo\Core\DI\Container;
use Neo\Core\DI\Exception\ContainerException;
use Neo\Core\Event\Attribute\AsListener;
use Neo\Core\Event\Exception\EventException;
use Neo\Core\Event\Interface\EventInterface;
use Neo\Core\Event\Interface\EventSubscriberInterface;
use Neo\Core\Event\Listener\ListenerRegistration;
use Neo\Core\Package\Interface\PackageInterface;
use Neo\Core\Profiler\ProfilerManager;
use Neo\Core\Utils\Scanner\ScannerAttributeManager;
use Neo\Core\Utils\Scanner\ScannerFileManager;

class EventManager
{
    /**
     * @var array<class-string, list<ListenerRegistration>>
     */
    private array $listeners = [];
    private Container $container;

    /**
     * @throws \ReflectionException
     * @throws ContainerException
     * @throws EventException
     */
    public function __construct(Container $container)
    {
        $this->container = $container;
        $this->scanListeners();
    }

    private function isDebug(): bool
    {
        try {
            return $this->container->get('event.configModule')->from('app')->get('environment') === 'dev';
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
            $decoded = json_decode(file_get_contents($cacheFile), true);
            $this->listeners = $this->hydrateListeners(is_array($decoded) ? $decoded : []);
            return;
        }

        $paths = [$this->container->get('listenersPath')];

        if ($this->container->has('packages')) {
            /** @var array<int, PackageInterface> $packages */
            foreach ($this->container->get('packages') as $package) {
                $path = $package->getListenersPath();
                if ($path !== null) {
                    $paths[] = $path;
                }
            }
        }

        $results = new ScannerFileManager()
            ->paths($paths)
            ->scan();

        foreach ($results as $result) {
            $this->processListenerClass($result->getFqcn());
        }

        foreach ($this->listeners as &$list) {
            usort($list, static fn (ListenerRegistration $a, ListenerRegistration $b): int => $b->getPriority() <=> $a->getPriority());
        }
        unset($list);

        if (!$this->isDebug()) {
            $cacheDir = dirname($cacheFile);
            if (!is_dir($cacheDir) && !mkdir($cacheDir, 0777, true) && !is_dir($cacheDir)) {
                throw new EventException(
                    title: 'Event Cache Directory Error',
                    message: sprintf("Unable to create event cache directory '%s'.", $cacheDir),
                    code: 500
                );
            }
            if (file_put_contents($cacheFile, json_encode($this->listeners, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)) === false) {
                throw new EventException(
                    title: 'Event Cache Write Error',
                    message: sprintf("Unable to write event cache file '%s'.", $cacheFile),
                    code: 500
                );
            }
        }
    }

    private function processListenerClass(string $fqcn): void
    {
        if (!class_exists($fqcn)) {
            return;
        }

        $results = new ScannerAttributeManager($fqcn)
            ->onClass()
            ->withAttribute(AsListener::class)
            ->scan();

        foreach ($results as $entry) {
            /** @var AsListener $listener */
            $listener = $entry->getAttribute();
            $this->listeners[$listener->event][] = new ListenerRegistration(
                class: $fqcn,
                priority: $listener->priority,
            );
        }

        if (new \ReflectionClass($fqcn)->implementsInterface(EventSubscriberInterface::class)) {
            foreach ($fqcn::getSubscribedEvents() as $eventClass => $method) {
                $this->listeners[$eventClass][] = new ListenerRegistration(
                    class: $fqcn,
                    priority: 0,
                    method: $method,
                );
            }
        }
    }

    /**
     * @param array<mixed> $decoded
     * @return array<class-string, list<ListenerRegistration>>
     */
    private function hydrateListeners(array $decoded): array
    {
        $listeners = [];

        foreach ($decoded as $eventClass => $entries) {
            if (!is_string($eventClass) || !is_array($entries)) {
                continue;
            }

            foreach ($entries as $entry) {
                if (!is_array($entry) || !isset($entry['class']) || !is_string($entry['class'])) {
                    continue;
                }

                /** @var array{class: class-string, priority: int, method?: string|array{0: string, 1?: int}} $entry */
                $listeners[$eventClass][] = ListenerRegistration::fromArray($entry);
            }
        }

        return $listeners;
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

            $listener = $meta->getInstance() ?? $this->container->get($meta->getClass());

            $method = $meta->resolveMethodName();

            if (!method_exists($listener, $method)) {
                throw new EventException(
                    title: 'Event Listener Error',
                    message: sprintf("Method '%s' does not exist on listener '%s'.", $method, $meta->getClass()),
                    code: 500
                );
            }

            $listener->$method($event);
            $called[] = $meta->getClass();
        }

        if (defined('NEO_PROFILER_ENABLED') && NEO_PROFILER_ENABLED) {
            $ms = (microtime(true) - $t0) * 1000;
            $ec = ProfilerManager::getInstance()->getCollector('events');
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
        $this->listeners[$eventClass][] = new ListenerRegistration(
            class: $listenerClass,
            priority: $priority,
            method: $method,
        );

        usort(
            $this->listeners[$eventClass],
            static fn (ListenerRegistration $a, ListenerRegistration $b): int => $b->getPriority() <=> $a->getPriority(),
        );
    }

    public function addListenerInstance(string $eventClass, object $instance, string $method = 'handle', int $priority = 0): void
    {
        $this->listeners[$eventClass][] = new ListenerRegistration(
            class: get_class($instance),
            priority: $priority,
            method: $method,
            instance: $instance,
        );

        usort(
            $this->listeners[$eventClass],
            static fn (ListenerRegistration $a, ListenerRegistration $b): int => $b->getPriority() <=> $a->getPriority(),
        );
    }

    public function addSubscriber(EventSubscriberInterface $subscriber): void
    {
        foreach ($subscriber::getSubscribedEvents() as $eventClass => $methodOrTuple) {
            [$method, $priority] = is_array($methodOrTuple)
                ? [$methodOrTuple[0], $methodOrTuple[1] ?? 0]
                : [$methodOrTuple, 0];

            $this->addListener($eventClass, get_class($subscriber), $priority, $method);
        }
    }

    /**
     * @return ($eventClass is null
     * ? array<class-string, list<ListenerRegistration>>
     * : list<ListenerRegistration>)
     */
    public function getListeners(?string $eventClass = null): array
    {
        if ($eventClass !== null) {
            return $this->listeners[$eventClass] ?? [];
        }
        return $this->listeners;
    }
}