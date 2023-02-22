<?php

namespace Bottledcode\SwytchFramework\Template\Attributes;

#[\Attribute(\Attribute::TARGET_CLASS)]
class Authenticated {
	public function __construct(public bool $visible) {}
}
