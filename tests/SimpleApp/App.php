<?php

namespace Bottledcode\SwytchFramework\Tests\SimpleApp;

use Bottledcode\SwytchFramework\Template\Attributes\Component;
use Bottledcode\SwytchFramework\Template\Traits\Refs;

#[Component('TestApp')]
class App {
	public function __construct(private \Bottledcode\SwytchFramework\Template\Compiler $compiler) {}
	public function render(string $name = '<>unknown<>'): string
	{
		return <<<HTML
<div>
Your name is {{$name}}.
<todoItem id="stable" diamond ></todoItem>
<blah:label id="labeltest" for="stable">
This is a label
</blah:label>
</div>
HTML;

	}
}
