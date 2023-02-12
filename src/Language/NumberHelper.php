<?php

namespace Bottledcode\SwytchFramework\Language;

use NumberFormatter;

final class NumberHelper
{

    /**
     * Handle parsing 3.14 or 3,14 to 3.14
     *
     * @param string $value
     *
     * @return float
     */
    public static function parseFloat(string $value): float
    {
        $float = (float)$value;
        $int = (int)$float;
        // if they are different, then we have a . decimal point
        if ($float <=> $int) {
            return $float;
        }
        static $numberFormat = new NumberFormatter('nl_NL', NumberFormatter::DECIMAL);

        // finally attempt to parse the value as a float
        return $numberFormat->parse($value);
    }

    /**
     * Format a float to a string
     *
     * @param float $value
     * @param string|LanguageAcceptor $language
     * @return string
     */
    public static function fromFloat(float $value, string|LanguageAcceptor $language): string
    {
        $locale = self::localeFromLanguage($language);
        static $formatters = [];
        if (!isset($formatters[$locale])) {
            $formatters[$locale] = new NumberFormatter($locale, NumberFormatter::DECIMAL);
        }

        return $formatters[$locale]->format($value, NumberFormatter::TYPE_DOUBLE);
    }

    /**
     * Convert a language to a locale
     *
     * @param string|LanguageAcceptor $language
     * @return string
     */
    public static function localeFromLanguage(string|LanguageAcceptor $language): string
    {
        if ($language instanceof LanguageAcceptor) {
            $language = $language->currentLanguage;
        }

        return str_replace('_', '-', $language);
    }

    /**
     * Format a float to a currency string
     *
     * @param float $value
     * @param string|LanguageAcceptor $language
     * @return string
     */
    public static function toCurrency(float $value, string|LanguageAcceptor $language): string
    {
        $locale = self::localeFromLanguage($language);
        static $formatters = [];
        if (!isset($formatters[$locale])) {
            $formatters[$locale] = new NumberFormatter($locale, NumberFormatter::CURRENCY);
        }

        return $formatters[$locale]->formatCurrency($value, 'EUR');
    }
}
