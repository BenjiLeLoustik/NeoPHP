<?php

namespace Neo\Core\Translation;

use Neo\Core\View\View;
use Twig\TwigFilter;

final class TranslationTwigExtension
{
    private TranslationManager $translator;

    public function __construct(
        View $view,
        TranslationManager $translator
    )
    {
        $this->translator = $translator;
        $this->register($view);
    }

    private function register(View $view): void
    {
        $translator = $this->translator;

        $view->registerTwigFunction('translate', function (
            string $key,
            ?string $defaultMessage = null,
            array $params = []
        ) use ($translator) {
            return $translator->translate($key, $defaultMessage, $params);
        });

        $view->registerTwigFunction('trans', function (
            string $key,
            array $params = [],
            ?string $defaultMessage = null
        ) use ($translator) {
            return $translator->translate($key, $defaultMessage, $params);
        });

        $view->registerTwigFilter('trans', function (
            string $key,
            array $params = [],
            ?string $defaultMessage = null
        ) use ($translator) {
            return $translator->translate($key, $defaultMessage, $params);
        });

        $view->registerTwigFunction('getLocales', [$translator, 'getLocales']);
        $view->registerTwigFunction('getLocale', [$translator, 'getLocale']);
        $view->registerTwigFunction('isEnabled_translation', [$translator, 'isEnabledTranslation']);
    }
}
