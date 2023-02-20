<?php

use Bottledcode\SwytchFramework\Template\Attributes\Component;
use Bottledcode\SwytchFramework\Template\Traits\Htmx;

#[Component('Test')]
class Test {
	use Htmx;

	public function onKeyUp(string $event): void {
	}

	public function render(string $stuff = '') {
		$script = "alert('Hello World!')";
		$style = 'h1 { color: red; }';
		return <<<HTML
<div>
	<style>{{$style}}</style>
	<h1>{{$stuff}}</h1>
	<input />
	<script>
	{{$script}}
	</script>
</div>
HTML;
	}
}
