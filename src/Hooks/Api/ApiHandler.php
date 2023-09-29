<?php

namespace Bottledcode\SwytchFramework\Hooks\Api;

use Bottledcode\SwytchFramework\Hooks\HandleRequestInterface;
use Bottledcode\SwytchFramework\Hooks\RequestType;

abstract class ApiHandler implements HandleRequestInterface
{
	protected RequestType $currentType;

	public function handles(RequestType $requestType): bool
	{
		$this->currentType = $requestType;
		return $requestType === RequestType::Api || $requestType === RequestType::Htmx;
	}
}
