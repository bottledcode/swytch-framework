<?php

namespace Bottledcode\SwytchFramework\Cache;

use Bottledcode\SwytchFramework\Cache\Control\Tokenizer;

/**
 * Marks the component as immutable
 */
#[\Attribute(\Attribute::TARGET_CLASS | \Attribute::TARGET_METHOD)]
readonly class NeverChanges extends AbstractCache
{
	private const YEAR = 604800;

	public function tokenize(Tokenizer $tokenizer): Tokenizer
	{
		// there is a component that requires a shorter cache, that wins
		if($tokenizer->maxAge < self::YEAR) {
			return $tokenizer;
		}

		return $tokenizer->with(
			maxAge: self::YEAR,
			mustRevalidate: false,
			proxyRevalidate: false,
			immutable: true,
			staleWhileRevalidating: null,
			staleIfError: null
		);
	}
}
