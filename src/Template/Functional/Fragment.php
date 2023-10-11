<?php

namespace Bottledcode\SwytchFramework\Template\Functional;

use Bottledcode\SwytchFramework\Template\Attributes\Component;

#[Component('swytch:fragment')]
class Fragment implements RewritingTag
{
	public string|null $id = null;

	public function render(string $id): string
	{
		$this->id = $id;
		return "<children></children>";
	}

	public function isItMe(string $id): bool
	{
		return $id === $this->id;
	}
}
