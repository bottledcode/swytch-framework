<?php

namespace Bottledcode\SwytchFramework\Hooks;

interface PreprocessInterface
{
	public function process(string $request): string;
}
