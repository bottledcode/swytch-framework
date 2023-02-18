<?php

use Bottledcode\SwytchFramework\Template\Attributes\Component;
use Bottledcode\SwytchFramework\Template\Traits\Callbacks;

#[Component('Test')]
class Test {
	use Callbacks;

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
