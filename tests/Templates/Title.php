<?php

use Bottledcode\SwytchFramework\Template\Attributes\Component;

#[Component('title')]
class Title
{
	public function __construct()
	{
	}

	public function render(): string
	{
		return "test title";
	}
}
