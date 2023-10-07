<?php

namespace Bottledcode\SwytchFramework\Template\Functional;

use Bottledcode\SwytchFramework\Template\Attributes\Component;

#[Component('swytch:defaultRoute')]
class DefaultRoute extends Route
{
	public function render(string|null $path = null, string|null $method = null): string
	{
		if (self::$foundRoute[$this->request] ?? false) {
			self::$foundRoute[$this->request] = true;

			return "<children></children>";
		}

		return '';
	}
}
