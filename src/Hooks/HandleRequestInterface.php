<?php

namespace Bottledcode\SwytchFramework\Hooks;

interface HandleRequestInterface
{
	public function handles(RequestType $requestType): bool;
}
