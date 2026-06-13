<?php
declare(strict_types=1);

namespace Neo\Core\Profiler\Collector;

use Neo\Core\Translation\Interface\TranslationCollectorInterface;
use Neo\Core\Translation\TranslationManager;

class TranslationCollector implements CollectorInterface, TranslationCollectorInterface
{
    private array $hits   = [];
    private array $misses = [];

    public function __construct(
        private readonly TranslationManager $manager
    ) {}

    public function getName(): string
    {
        return 'translation';
    }

    public function record(string $key, string $result, bool $found): void
    {
        if ($found) {
            $this->hits[] = ['key' => $key, 'result' => $result];
        } else {
            $this->misses[] = ['key' => $key, 'result' => $result];
        }
    }

    public function collect(): array
    {
        $locale = $this->manager->getLocale();
        $locales = $this->manager->getLocales();
        $enabled = $this->manager->isEnabledTranslation();

        return [
            'enabled' => $enabled,
            'locale' => $locale,
            'locales' => $locales,
            'hits_count' => count($this->hits),
            'misses_count' => count($this->misses),
            'hits' => $this->hits,
            'misses' => $this->misses,
        ];
    }
}