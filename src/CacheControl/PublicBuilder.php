<?php

namespace Bottledcode\SwytchFramework\CacheControl;

class PublicBuilder extends PrivateBuilder
{
	public function differentSharedAge(int $seconds): PrivateBuilder
	{
		return new PrivateBuilder(
			[...$this->values, "s-maxage={$seconds}"],
			$this->etagRequired,
			$this->score - 10,
			$this->tag
		);
	}
}
