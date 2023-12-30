<?php

namespace Bottledcode\SwytchFramework\Cache;

use Bottledcode\SwytchFramework\Cache\Control\Tokenizer;

/**
 * Sets the cache max age (or s-maxage if shared is true)
 */
#[\Attribute(\Attribute::TARGET_METHOD | \Attribute::TARGET_CLASS | \Attribute::IS_REPEATABLE)]
readonly class MaxAge extends AbstractCache
{
	public function __construct(public int $age, public bool $shared = false)
	{
	}

	public function tokenize(Tokenizer $tokenizer): Tokenizer
	{
		$previousAge = $this->shared ? $tokenizer->sMaxAge : $tokenizer->maxAge;

		// keep the shortest age
		if ($this->age > $previousAge && $previousAge !== null) {
			return $tokenizer;
		}

		// if age = 0, this is the same as no-store
		if ($this->age === 0) {
			return (new NeverCache())->tokenize($tokenizer);
		}

		// if we were previously not storing the page, bail
		if ($tokenizer->noStore) {
			return $tokenizer;
		}

		// ensure immutable is unset
		return $this->shared ? $tokenizer->with(sMaxAge: $this->age, immutable: false) : $tokenizer->with(
			maxAge: $this->age,
			immutable: false
		);
	}
}
