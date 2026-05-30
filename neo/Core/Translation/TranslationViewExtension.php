<?php

namespace Neo\Core\Translation;

use Neo\Core\View\Interface\TwigExtensionInterface;

class TranslationViewExtension implements TwigExtensionInterface
{

    public function __construct(
        private readonly TranslationManager $translator
    ){}

    public function getFunctions(): array
    {
        return [
            'translate' => [
                'callable' => fn(string $key, ?string $default = null, array $params = []) => $this->translator->translate($key, $default, $params),
                'options' => [],
            ],
            'trans' => [
                'callable' => fn(string $key, ?string $default = null, array $params = []) => $this->translator->translate($key, $default, $params),
                'options' => [],
            ],
            'getLocales' => [
                'callable' => [$this->translator, 'getLocales'],
                'options' => [],
            ],
            'getLocale' => [
                'callable' => [$this->translator, 'getLocale'],
                'options' => [],
            ],
            'isEnabled_translation' => [
                'callable' => [$this->translator, 'isEnabled_translation'],
                'options' => [],
            ],
        ];
    }

    public function getFilters(): array
    {
        return [
            'trans' => [
                'callable' => fn(string $key, array $params = [], ?string $default = null) => $this->translator->translate($key, $default, $params),
                'options' => [],
            ],
        ];
    }
}