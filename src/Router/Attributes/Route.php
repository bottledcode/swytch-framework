<?php

namespace Bottledcode\SwytchFramework\Router\Attributes;

use Attribute;
use Bottledcode\SwytchFramework\Router\Method;

#[Attribute(Attribute::TARGET_METHOD | Attribute::IS_REPEATABLE)]
readonly class Route
{
	public function __construct(public Method $method, public string $path)
	{
	}
}
