<?php

namespace Bottledcode\SwytchFramework\Router\Attributes;

use Attribute;
use BackedEnum;

#[Attribute(Attribute::TARGET_METHOD)]
class Authorized
{
	public array $roles;

	public function __construct(BackedEnum ...$role)
	{
		$this->roles = $role;
	}
}
