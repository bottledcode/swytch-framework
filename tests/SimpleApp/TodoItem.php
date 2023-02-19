<?php

use Bottledcode\SwytchFramework\Template\Attributes\Component;
use Bottledcode\SwytchFramework\Template\Traits\FancyClasses;

#[Component('TodoItem')]
class TodoItem {
	public function __construct() {}

	public function render(array $stuff) {
		return <<<HTML
<hi>{{$stuff['name']}}</hi>
<form hx-post="/test">
Verify state: <input name="verify" >
</form>
HTML;
	}
}
