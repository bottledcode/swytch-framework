<?php

namespace Bottledcode\SwytchFramework\CacheControl;

class WillChangeBuilder extends Builder
{
	public function never(): SharedBuilder
	{
		return new SharedBuilder(
			array_merge($this->values, ['immutable', 'max-age=604800']),
			$this->etagRequired,
			$this->score + 1,
			$this->tag
		);
	}

	public function often(): SharedBuilder
	{
		return new SharedBuilder(array_merge($this->values, ['no-cache']), true, $this->score + 2, $this->tag);
	}

	public function periodically(int $seconds): MustCheckBuilder
	{
		return new MustCheckBuilder(
			array_merge($this->values, ["max-age=$seconds"]),
			$this->etagRequired,
			$this->score + 3,
			$this->tag
		);
	}
}
