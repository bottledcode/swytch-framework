<?php

namespace Bottledcode\SwytchFramework\CacheControl;

class SharedBuilder extends Builder
{
	public function shared(): PublicBuilder
	{
		return new PublicBuilder(
			[...$this->values, 'public'],
			$this->etagRequired,
			$this->score + 2,
			$this->tag
		);
	}

	public function notShared(): PrivateBuilder
	{
		return new PrivateBuilder([...$this->values, 'private'], $this->etagRequired, 0, $this->tag);
	}
}
