<?php

namespace Bottledcode\SwytchFramework\Cache;

use Bottledcode\SwytchFramework\Cache\Control\Tokenizer;

readonly class CachePublic extends AbstractCache
{
	public function tokenize(Tokenizer $tokenizer): Tokenizer
	{
		if (!$tokenizer->public) {
			return $tokenizer;
		}

		return $tokenizer->with(public: true);
	}
}
