<?php

namespace Bottledcode\SwytchFramework\Template\Attributes;

#[\Attribute(\Attribute::TARGET_CLASS)]
class Authorized
{
	public array $roles;
	public function __construct(public bool $visible, \BackedEnum ...$role)
	{
		$this->roles = $role;
	}
}
