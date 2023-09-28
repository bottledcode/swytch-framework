<?php

namespace Bottledcode\SwytchFramework\Template\Attributes;

use Attribute;

#[Attribute(Attribute::TARGET_CLASS)]
readonly class Component
{
	/**
	 * @param string $name The name of the component
	 * @param bool $isContainer Whether the component is a container or not
	 */
	public function __construct(public string $name, public bool $isContainer = false)
	{
	}
}
