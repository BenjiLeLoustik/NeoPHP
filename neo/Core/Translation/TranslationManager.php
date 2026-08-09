<?php

namespace Neo\Core\Translation;

use Neo\Core\DI\Container;
use Neo\Core\DI\Exception\ContainerException;
use Neo\Core\Translation\Domain\TranslationDomain;
use Neo\Core\Translation\Exception\TranslationException;
use Neo\Core\Translation\Interface\TranslatorInterface;
use Neo\Core\Translation\Loader\TranslationLoader;
use Neo\Core\Translation\Locale\LocaleManager;
use Neo\Core\Translation\Writer\TranslationWriter;

class TranslationManager implements TranslatorInterface
{
    protected Container $container;
    private string $locale;
    private TranslationLoader $loader;
    private TranslationWriter $writer;
    private bool $autoWrite;
    private bool $enabled;

    /** @var list<array{key: string, result: string, found: bool, domain: string}> */
    private static array $records = [];

    /**
     * @throws ContainerException
     */
    public function __construct(Container $container)
    {
        $this->container = $container;

        $config = $container->get('translation.configModule')->from('app');

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
    public function translate(string $text, array $replace = [], ?string $domain = null): string
    {
        $domain = TranslationDomain::normalize($domain);

        if (!$this->enabled) {
            return $this->replace($text, $replace);
        }

        $translations = $this->loader->load($this->locale, $domain);
        $found = array_key_exists($text, $translations);
        $result = $found ? $translations[$text] : $text;

        self::$records[] = [
            'key' => $text,
            'result' => $result,
            'found' => $found,
            'domain' => $domain,
        ];

        if (!$found && $this->autoWrite) {
            $this->writer->ensure($this->locale, $text, $domain);
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
            ->get('translation.configModule')
            ->from('app')
            ->get('translation');

        $availableLocales = $translationConfig['available_locales'] ?? [];
        $cookie = $this->container->get('translation.clientModule')->cookie();

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
            ->get('translation.configModule')
            ->from('app')
            ->get('translation.available_locales') ?? [];
    }

    public function isEnabledTranslation(): bool
    {
        return $this->enabled;
    }

    /**
     * @return list<array{key: string, result: string, found: bool, domain: string}>
     */
    public function getRecords(): array
    {
        return self::$records;
    }
}