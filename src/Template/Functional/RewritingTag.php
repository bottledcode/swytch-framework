<?php

namespace Bottledcode\SwytchFramework\Template\Functional;

interface RewritingTag
{
	public function isItMe(string|null $id): bool;
}
