<?php

namespace Bottledcode\SwytchFramework\Router\Attributes;

use PhpParser\Node\Attribute;

#[\Attribute(\Attribute::TARGET_PARAMETER, \Attribute::TARGET_PROPERTY)]
class From {
	public function __construct(public string $realName) {
	}
}
