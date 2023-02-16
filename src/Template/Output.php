<?php

namespace Bottledcode\SwytchFramework\Template;

use Masterminds\HTML5\Serializer\OutputRules;

class Output extends OutputRules
{
	public function enc($text, $attribute = false)
	{
		return $text;
	}
}
