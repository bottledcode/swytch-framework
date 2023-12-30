<?php

namespace Bottledcode\SwytchFramework\Cache;

use Bottledcode\SwytchFramework\Cache\Control\Tokenizer;

#[\Attribute(\Attribute::TARGET_METHOD | \Attribute::TARGET_CLASS)]
readonly class NeverCache extends AbstractCache
{
	public function tokenize(Tokenizer $tokenizer): Tokenizer
	{
		// this trumps everything and ensures the page is never cached!
		return $tokenizer->with(
			maxAge: null,
			sMaxAge: null,
			noCache: false,
			mustRevalidate: false,
			proxyRevalidate: false,
			noStore: true,
			public: false,
			immutable: false,
			staleWhileRevalidating: null,
			staleIfError: null
		);
	}
}
