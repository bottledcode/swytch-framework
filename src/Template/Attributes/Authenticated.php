<?php

namespace Bottledcode\SwytchFramework\Template\Attributes;

use Attribute;

#[Attribute(Attribute::TARGET_CLASS)]
class Authenticated
{
	public function __construct(public bool $visible)
	{
	}
}
