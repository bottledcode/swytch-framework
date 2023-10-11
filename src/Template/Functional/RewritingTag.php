<?php

namespace Bottledcode\SwytchFramework\Template\Functional;

interface RewritingTag
{
	public function isItMe(string $id): bool;
}
