<?php

use Bottledcode\SwytchFramework\Template\Attributes\Component;
use Bottledcode\SwytchFramework\Template\Traits\Refs;

#[Component('App')]
class App {
	use Refs;

	public function __construct(private \Bottledcode\SwytchFramework\Template\Compiler $compiler) {}
	public function render(string $name = '<>unknown<>'): string
	{
		$arr = ['name' => 'Bob'];

		return <<<HTML
<div>
Your name is {{$name}}.
<form hx-post="/">
<todoItem stuff="{{$this->ref($arr)}}" />
</form>
</div>
HTML;

	}
}
