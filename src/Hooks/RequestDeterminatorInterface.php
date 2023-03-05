<?php

namespace Bottledcode\SwytchFramework\Hooks;

interface RequestDeterminatorInterface
{
	public function currentRequestIs(string $body, RequestType $current): RequestType;
}
