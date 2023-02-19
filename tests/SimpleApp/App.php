<?php

use Bottledcode\SwytchFramework\Template\Attributes\Component;
use Bottledcode\SwytchFramework\Template\ComponentInterface;

#[Component('App')]
class App {
	public function aboutToRender(): void
	{
	}

	public function render(string $name = '<>unknown<>'): string
	{
		return <<<HTML
<div>
Your name is {{$name}}.
<form hx-post="/">
</form>
</div>
HTML;

	}
}
