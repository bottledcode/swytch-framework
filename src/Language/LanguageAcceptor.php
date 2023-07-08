<?php

namespace Bottledcode\SwytchFramework\Language;

use Bottledcode\SwytchFramework\Hooks\Handler;
use Bottledcode\SwytchFramework\Hooks\HandleRequestInterface;
use Bottledcode\SwytchFramework\Hooks\PreprocessInterface;
use Bottledcode\SwytchFramework\Hooks\RequestType;
use Gettext\Loader\MoLoader;
use Gettext\Translator;
use Gettext\TranslatorFunctions;
use Psr\Http\Message\ServerRequestInterface;

#[Handler(1)]
class LanguageAcceptor implements PreprocessInterface, HandleRequestInterface
{
	public readonly string $currentLanguage;

	/**
	 * @param string $locale
	 * @param array<string> $supportedLocales
	 * @param string $languageDir
	 */
	public function __construct(string $locale, array $supportedLocales, protected string $languageDir)
	{
		$detectedLocale = array_column(array_map(static fn($x) => explode(';', $x), explode(',', $locale)), 0);
        $detectedLocale = array_unique(array_merge(...array_map(static fn($x) => [explode('-', $x)[0], $x], $detectedLocale)));
		$this->currentLanguage = array_values(
			array_intersect($supportedLocales, $detectedLocale)
		)[0] ?? $supportedLocales[0];
	}

	public function preprocess(ServerRequestInterface $request, RequestType $type): ServerRequestInterface
	{
		$this->loadLanguage();
		return $request;
	}

	public function loadLanguage(): void
	{
		if (file_exists($this->languageDir . $this->currentLanguage . '.mo')) {
			$loader = (new MoLoader())->loadFile($this->languageDir . $this->currentLanguage . '.mo');
			$translator = Translator::createFromTranslations($loader);
		} else {
			$translator = new Translator();
		}

		TranslatorFunctions::register($translator);
	}

	public function handles(RequestType $requestType): bool
	{
		return true;
	}
}
