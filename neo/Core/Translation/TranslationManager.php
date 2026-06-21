<?php

namespace Neo\Core\Translation;

use Neo\Core\DI\Container;
use Neo\Core\DI\Exception\ContainerException;
use Neo\Core\Http\Client\Cookie\Cookie;
use Neo\Core\Translation\Exception\TranslationException;
use Neo\Core\Translation\Interface\TranslationCollectorInterface;
use Neo\Core\Translation\Interface\TranslatorInterface;
use Neo\Core\Translation\Loader\TranslationLoader;
use Neo\Core\Translation\Locale\LocaleManager;
use Neo\Core\Translation\Writer\TranslationWriter;
use Neo\Core\Utils\Config\Config;

class TranslationManager implements TranslatorInterface
{
    protected Container $container;
    private string $locale;
    private TranslationLoader $loader;
    private TranslationWriter $writer;
    private bool $autoWrite;
    private bool $enabled;
    private ?TranslationCollectorInterface $collector = null;

    /**
     * @throws ContainerException
     */
    public function __construct(Container $container)
    {
        $this->container = $container;

        $config = $container->get(Config::class)->from('app');

        $this->enabled = (bool) ($config->get('translation.enabled') ?? false);
        $this->autoWrite = $config->get('environment') === 'dev';
        $this->locale = LocaleManager::resolve($container);
        $this->loader = new TranslationLoader();
        $this->writer = new TranslationWriter($this->loader);
    }

    /**
     * @throws TranslationException
     * @throws ContainerException
     */
    public function setLocale(string $locale, int $lifetime = 31536000): void
    {
        $translationConfig = $this->container
            ->get(Config::class)
            ->from('app')
            ->get('translation');

        $availableLocales = $translationConfig['available_locales'] ?? [];
        $cookie = $this->container->get(Cookie::class);

        if (!empty($availableLocales) && !isset($availableLocales[$locale])) {
            throw new TranslationException(
                title: 'Invalid Locale',
                message: sprintf(
                    "Locale '%s' is not available. Accepted locales: %s.",
                    $locale,
                    implode(', ', array_keys($availableLocales))
                ),
                code: 400
            );
        }

        $this->locale = $locale;
        $cookie->set('lang', $locale, time() + $lifetime, '/', null, false, true);
    }

    /**
     * @param array<string, mixed> $replace
     * @throws TranslationException
     */
    public function translate(
        string $key,
        ?string $defaultMessage = null,
        array $replace = []
    ): string {
        if (!$this->enabled) {
            return $this->replace($defaultMessage ?? $key, $replace);
        }

        if (!$this->isValidKey($key)) {
            throw new TranslationException(
                title: 'Invalid Translation Key',
                message: sprintf("Translation key '%s' is invalid. Expected format: 'file.key'.", $key),
                code: 500
            );
        }

        $translated = $this->resolve($key);
        $found = $translated !== $key;
        $this->collector?->record($key, $found ? $translated : ($defaultMessage ?? $key), $found);


        if ($translated === $key) {
            if ($this->autoWrite) {
                $this->registerKeyIfNotExists($key, $defaultMessage ?? $key);
            }

            return $this->replace($defaultMessage ?? $key, $replace);
        }

        return $this->replace($translated, $replace);
    }

    private function resolve(string $key): string
    {
        if (!str_contains($key, '.')) {
            return $key;
        }

        [$file, $path] = explode('.', $key, 2);
        $segments = explode('.', $path);
        $translations = $this->loader->load($this->locale, $file);

        $value = $translations;
        foreach ($segments as $segment) {
            if (!isset($value[$segment])) {
                return $key;
            }
            $value = $value[$segment];
        }

        return is_string($value) ? $value : $key;
    }

    /**
     * @param array<string, mixed> $replace
     */
    private function replace(string $text, array $replace): string
    {
        foreach ($replace as $key => $value) {
            $text = str_replace(':' . $key, (string) $value, $text);
        }

        return $text;
    }

    public function getLocale(): string
    {
        return $this->locale;
    }

    /**
     * @return array<string, mixed>
     * @throws ContainerException
     */
    public function getLocales(): array
    {
        return $this->container
            ->get(Config::class)
            ->from('app')
            ->get('translation.available_locales') ?? [];
    }

    public function isEnabledTranslation(): bool
    {
        return $this->enabled;
    }

    /**
     * @throws TranslationException
     */
    public function registerKeyIfNotExists(
        string $key,
        ?string $value = null,
        bool $forceUpdate = false
    ): void {
        if (!$this->enabled || !str_contains($key, '.')) {
            return;
        }

        if (!$this->isValidKey($key)) {
            throw new TranslationException(
                title: 'Invalid Translation Key',
                message: sprintf("Translation key '%s' is invalid. Expected format: 'file.key'.", $key),
                code: 500
            );
        }

        [$file, $path] = explode('.', $key, 2);
        $segments = explode('.', $path);
        $translations = $this->loader->load($this->locale, $file);

        $existingValue = $translations;
        foreach ($segments as $segment) {
            if (!isset($existingValue[$segment])) {
                $existingValue = null;
                break;
            }
            $existingValue = $existingValue[$segment];
        }

        if ($existingValue === null || $forceUpdate) {
            $this->writer->ensure($this->locale, $file, $segments, $value ?? $key);
        }
    }

    private function isValidKey(string $key): bool
    {
        return (bool) preg_match('/^[a-zA-Z0-9_\-]+(\.[a-zA-Z0-9_\-]+)+$/', $key);
    }


    public function setCollector(TranslationCollectorInterface $collector): void
    {
        $this->collector = $collector;
    }
}