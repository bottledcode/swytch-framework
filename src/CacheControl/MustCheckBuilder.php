<?php

namespace Bottledcode\SwytchFramework\CacheControl;

class MustCheckBuilder extends Builder
{
	public function alwaysCheck(): SharedBuilder
	{
		return new SharedBuilder(array_merge($this->values, ['no-cache']), true, $this->score + 1, $this->tag);
	}

	public function ifStale(): SharedBuilder
	{
		return new SharedBuilder(array_merge($this->values, ['revalidate']), true, $this->score + 1, $this->tag);
	}

	public function neverCheck(): SharedBuilder
	{
		return new SharedBuilder(
			array_merge($this->values, ['immutable']),
			$this->etagRequired,
			$this->score + 2,
			$this->tag
		);
	}
}
