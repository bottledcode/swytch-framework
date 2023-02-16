<?php

use Bottledcode\SwytchFramework\Template\Attributes\Component;

#[Component('VariableTemplate')]
class VariableTemplate {
	public function render(): string
	{
		$test = 'this is from a variable';
		return <<<HTML
variable result: $test
HTML;
	}
}
