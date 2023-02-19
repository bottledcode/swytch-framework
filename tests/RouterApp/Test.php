<?php

use Bottledcode\SwytchFramework\Template\Attributes\Component;
use Bottledcode\SwytchFramework\Template\Traits\Htmx;

#[Component('Test')]
class Test {
	use Htmx;

	public function onKeyUp(string $event): void {
	}

	public function render(string $stuff = '') {
		return <<<HTML
<div>
	<h1>{{$stuff}}</h1>
	<input onkeyup="{{$this->onKeyUp}}" />
</div>
HTML;
	}
}
