<?php

namespace Bottledcode\SwytchFramework\Tests\SimpleApp;

use Bottledcode\SwytchFramework\Template\Attributes\Component;

#[Component('todoitem')]
class TodoItem
{
	public function __construct()
	{
	}

	public function render(bool $diamond = false)
	{
		$diamond = $diamond ? 'diamond' : 'square';

		return <<<HTML
<div>
 I am a {{$diamond}}
</div>
HTML;
	}
}
