<?php

use Bottledcode\SwytchFramework\Template\Attributes\Component;

#[Component('Nested')]
class Nested
{
	public function render()
	{
		return <<<HTML
<div>
This is a title:
<title/>
</div>
HTML;
	}
}
