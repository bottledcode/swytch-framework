<?php

namespace Bottledcode\SwytchFramework\Router\Attributes;

use Attribute;
use Bottledcode\SwytchFramework\Router\Method;
use Closure;

#[Attribute(Attribute::TARGET_METHOD)]
readonly class SseRoute extends Route
{
	public function __construct(Method $method, string $path)
	{
		parent::__construct($method, $path);
	}
}
