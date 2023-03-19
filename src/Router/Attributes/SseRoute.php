<?php

namespace Bottledcode\SwytchFramework\Router\Attributes;

use Attribute;
use Closure;

#[Attribute(Attribute::TARGET_METHOD)]
readonly class SseRoute
{
	public function __construct(public Closure $messageGenerator, public int $retryMs = 2000)
	{
	}
}
