<?php

namespace Bottledcode\SwytchFramework\Hooks;

use Attribute;

#[Attribute(Attribute::TARGET_CLASS)]
class Handler
{
	public function __construct(public readonly int $priority)
	{
	}
}
