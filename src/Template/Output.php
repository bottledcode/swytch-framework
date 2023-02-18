<?php

namespace Bottledcode\SwytchFramework\Template;

use Laminas\Escaper\Escaper;
use Masterminds\HTML5\Serializer\OutputRules;

class Output extends OutputRules
{
	private Escaper $escaper;

	public function setEscaper(Escaper $escaper): void
	{
		$this->escaper = $escaper;
	}

	public function enc($text, $attribute = false)
	{
		preg_match_all('@\\{([^\/\{\}\x00-\x1F=]++)@', $text, $matches);
		foreach ($matches[1] as $match) {
			$match = trim($match, '{}');
			$text = str_replace("{{$match}}",$attribute ? $this->escaper->escapeHtmlAttr($match) : $this->escaper->escapeHtml($match), $text);
		}
		return $text;
	}
}
