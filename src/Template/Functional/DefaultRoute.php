<?php

namespace Bottledcode\SwytchFramework\Template\Functional;

use Bottledcode\SwytchFramework\Template\Attributes\Component;

#[Component('DefaultRoute')]
class DefaultRoute extends Route {
	public function render(string $render, string|null $path = null, string|null $method = null): string {
		if($this->foundRoute()) {
			return '';
		}
		return $render;
	}
}
