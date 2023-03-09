<?php

namespace Bottledcode\SwytchFramework\Template\Attributes;

use Attribute;
use BackedEnum;

#[Attribute(Attribute::TARGET_CLASS)]
class Authorized
{
	/**
	 * @var array|BackedEnum[] $roles
	 */
	public array $roles;

	public function __construct(public bool $visible, BackedEnum ...$role)
	{
		$this->roles = $role;
	}
}
