<?php

namespace Bottledcode\SwytchFramework\Hooks;

use Psr\Http\Message\ServerRequestInterface;

interface RequestDeterminatorInterface
{
	public function currentRequestIs(ServerRequestInterface $request, RequestType $type): RequestType;
}
