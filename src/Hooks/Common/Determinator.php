<?php

namespace Bottledcode\SwytchFramework\Hooks\Common;

use Bottledcode\SwytchFramework\Hooks\Handler;
use Bottledcode\SwytchFramework\Hooks\RequestDeterminatorInterface;
use Bottledcode\SwytchFramework\Hooks\RequestType;
use Psr\Http\Message\ServerRequestInterface;

#[Handler(10)]
class Determinator implements RequestDeterminatorInterface
{
	public function currentRequestIs(ServerRequestInterface $request, RequestType $type): RequestType
	{
		return match (substr($request->getUri()->getPath(), 0, 4)) {
			'/api' => count($request->getHeader('HX-Request')) ? RequestType::Htmx : RequestType::Api,
			default => RequestType::Browser,
		};
	}
}
