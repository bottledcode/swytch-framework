<?php

namespace Bottledcode\SwytchFramework\Hooks;

#[\Attribute(\Attribute::TARGET_CLASS)]
class Handler
{
	public function __construct(public readonly int $priority)
	{
	}
}
