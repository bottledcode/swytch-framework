<?php

namespace Bottledcode\SwytchFramework\Template;

use Masterminds\HTML5\Parser\Scanner;

class CustomScanner extends Scanner
{
	public function __construct(string $html, array $components = [])
	{
		parent::__construct($html);
	}

	public function inject()
	{
	}
}
