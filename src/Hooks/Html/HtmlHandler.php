<?php

namespace Bottledcode\SwytchFramework\Hooks\Html;

use Bottledcode\SwytchFramework\Hooks\HandleRequestInterface;
use Bottledcode\SwytchFramework\Hooks\RequestType;

abstract class HtmlHandler implements HandleRequestInterface
{
	public function handles(RequestType $requestType): bool
	{
		return $requestType === RequestType::Browser;
	}
}
