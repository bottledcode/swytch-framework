<?php

namespace Bottledcode\SwytchFramework\Template;

use Laminas\Escaper\Escaper;
use Masterminds\HTML5\Serializer\OutputRules;

class Output extends OutputRules
{
	public const ESCAPE_SEQUENCE = '@\\{([^\/\{\}\x00-\x1F=]++)@';

	private Escaper $escaper;

	public function setEscaper(Escaper $escaper): void
	{
		$this->escaper = $escaper;
	}

	public function enc($text, $attribute = false)
	{
		preg_match_all(self::ESCAPE_SEQUENCE, $text, $matches);
		foreach ($matches[1] as $match) {
			$match = trim($match, '{}');
			$text = str_replace("{{$match}}",$attribute ? $this->escaper->escapeHtmlAttr($match) : $this->escaper->escapeHtml($match), $text);
		}
		// handle 'false' variables
		$text = str_replace('{}', '', $text);
		return $text;
	}
}
