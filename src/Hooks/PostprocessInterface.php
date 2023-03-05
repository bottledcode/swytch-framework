<?php

namespace Bottledcode\SwytchFramework\Hooks;

interface PostprocessInterface
{
	public function process(string $request): string;
}
