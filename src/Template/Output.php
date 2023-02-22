<?php

namespace Bottledcode\SwytchFramework\Template;

use Bottledcode\SwytchFramework\Template\Interfaces\EscaperInterface;
use Laminas\Escaper\Escaper;
use Masterminds\HTML5\Serializer\OutputRules;

class Output extends OutputRules
{
	public const ESCAPE_SEQUENCE = '@\\{([^}]+)@';

	private Escaper $escaper;

	private EscaperInterface $blobs;

	public function __construct($output, $options = array())
	{
		parent::__construct($output, $options);
	}

	public function setBlobber(EscaperInterface $blobs): void {
		$this->blobs = $blobs;
	}

	public function setEscaper(Escaper $escaper): void
	{
		$this->escaper = $escaper;
	}

	public function element($ele)
	{
		if ($ele->nodeName === 'script') {
			foreach ($ele->childNodes as $child) {
				if ($child instanceof \DOMCharacterData) {
					$child->data = $this->script($child->data);
				}
			}
		}
		if($ele->nodeName === 'style') {
			foreach ($ele->childNodes as $child) {
				if ($child instanceof \DOMCharacterData) {
					$child->data = $this->style($child->data);
				}
			}
		}

		parent::element($ele);
	}

	private function style(string $text): string {
		return $this->blobs->replaceBlobs($text, fn($blob) => $this->escaper->escapeCss($blob));

		preg_match_all(self::ESCAPE_SEQUENCE, $text, $matches);
		foreach ($matches[1] as $match) {
			$match = trim($match, '{}');
			$text = str_replace(
				"{{$match}}",
				$this->escaper->escapeCss($match),
				$text
			);
		}
		$text = str_replace('{}', '', $text);
		return $text;
	}

	private function script(string $text): string {
		return $this->blobs->replaceBlobs($text, fn($blob) => $this->escaper->escapeJs($blob));

		preg_match_all(self::ESCAPE_SEQUENCE, $text, $matches);
		foreach ($matches[1] as $match) {
			$match = trim($match, '{}');
			$text = str_replace(
				"{{$match}}",
				$this->escaper->escapeJs($match),
				$text
			);
		}
		$text = str_replace('{}', '', $text);
		return $text;
	}

	public function enc($text, $attribute = false)
	{
		return $this->blobs->replaceBlobs($text, fn($blob) => $attribute ? $this->escaper->escapeHtmlAttr($blob) : $this->escaper->escapeHtml($blob));
		preg_match_all(self::ESCAPE_SEQUENCE, $text, $matches);
		foreach ($matches[1] as $match) {
			$match = trim($match, '{}');
			$text = str_replace(
				"{{$match}}",
				$attribute ? $this->escaper->escapeHtmlAttr($match) : $this->escaper->escapeHtml($match),
				$text
			);
		}
		// handle 'false' variables
		$text = str_replace('{}', '', $text);
		return $text;
	}
}
