<?php

namespace Bottledcode\SwytchFramework\Cache\Control;

interface GeneratesEtagInterface
{
	public function getEtagComponents(): array;
}
