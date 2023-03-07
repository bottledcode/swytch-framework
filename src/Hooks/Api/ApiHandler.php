<?php

namespace Bottledcode\SwytchFramework\Hooks\Api;

use Bottledcode\SwytchFramework\Hooks\HandleRequestInterface;
use Bottledcode\SwytchFramework\Hooks\RequestType;

abstract class ApiHandler implements HandleRequestInterface
{
	public function handles(RequestType $requestType): bool
	{
		return $requestType === RequestType::Api || $requestType === RequestType::Htmx;
	}
}
