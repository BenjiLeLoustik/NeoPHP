<?php

namespace Neo\Core\Translation;

use Neo\Core\DI\Container;
use Neo\Core\Http\Client\Cookie;
use Neo\Core\Translation\Exception\TranslationException;
use Neo\Core\Utils\Config;

final class LocaleManager
{
    public static function resolve(Container $container): string
    {
        $cookie            = $container->get(Cookie::class);
        $translationConfig = $container->get(Config::class)->from('app')->get('translation');

        $availableLocales = $translationConfig['available_locales'] ?? [];
        $defaultLocale    = strtolower($translationConfig['default_locale'] ?? 'fr');

        if ($cookie->has('lang')) {
            $cookieLang = strtolower($cookie->get('lang'));
            if (empty($availableLocales) || isset($availableLocales[$cookieLang])) {
                return $cookieLang;
            }
        }

        if (empty($availableLocales) || isset($availableLocales[$defaultLocale])) {
            return $defaultLocale;
        }

        return 'fr';
    }
}