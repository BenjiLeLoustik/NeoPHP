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
     * @param array<string, mixed> $replace
     * @throws TranslationException
     */
    public function translate(string $text, array $replace = []): string
    {
        if (!$this->enabled) {
            return $this->replace($text, $replace);
        }

        $translations = $this->loader->load($this->locale);
        $found = array_key_exists($text, $translations);
        $result = $found ? $translations[$text] : $text;

        $this->collector?->record($text, $result, $found);

        if (!$found && $this->autoWrite) {
            $this->writer->ensure($this->locale, $text);
        }

        return $this->replace($result, $replace);
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
        $cookie           = $this->container->get(Cookie::class);

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

    public function setCollector(TranslationCollectorInterface $collector): void
    {
        $this->collector = $collector;
    }
}