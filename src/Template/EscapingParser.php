<?php

namespace Bottledcode\SwytchFramework\Template;

use Masterminds\HTML5\Parser\Tokenizer;

class EscapingParser extends Tokenizer {
	protected function consumeData()
	{
		$token = $this->scanner->current();

		// are we entering an area that should be escaped?
		if($token === '{') {
			$toEscape = $this->readUntilSequence('}');
			$this->events->text($toEscape);
		}

		return parent::consumeData();
	}
}
