<?php

namespace Bottledcode\SwytchFramework\Language;

use Gettext\Loader\MoLoader;
use Gettext\Translator;
use Gettext\TranslatorFunctions;

abstract class LanguageAcceptor
{
    public readonly string $currentLanguage;

    /**
     * @param string $locale
     * @param array<string> $supportedLocales
     */
    public function __construct(string $locale, array $supportedLocales)
    {
        $detectedLocale = array_column(array_map(static fn($x) => explode(';', $x), explode(',', $locale)), 0);
        $this->currentLanguage = array_values(
            array_intersect($supportedLocales, $detectedLocale)
        )[0] ?? $supportedLocales[0];
    }

    public function loadLanguage(): void
    {
        if (file_exists($this->getLanguageDir() . $this->currentLanguage . '.mo')) {
            $loader = (new MoLoader())->loadFile($this->getLanguageDir() . $this->currentLanguage . '.mo');
            $translator = Translator::createFromTranslations($loader);
        } else {
            $translator = new Translator();
        }

        TranslatorFunctions::register($translator);
    }

    abstract protected function getLanguageDir(): string;
}
