<?php

namespace Bottledcode\SwytchFramework\Router\Attributes;

#[\Attribute(\Attribute::TARGET_METHOD)]
class Authorized
{
	public array $roles;

	public function __construct(\BackedEnum ...$role)
	{
		$this->roles = $role;
	}
}
