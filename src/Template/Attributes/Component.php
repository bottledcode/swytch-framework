<?php

namespace Bottledcode\SwytchFramework\Template\Attributes;

use Attribute;

#[Attribute(Attribute::TARGET_CLASS)]
class Component
{
	public function __construct(public string $name)
	{
	}
}
