<?php

namespace Bottledcode\SwytchFramework\Cache;

use Bottledcode\SwytchFramework\Cache\Control\Tokenizer;

#[\Attribute(\Attribute::TARGET_METHOD | \Attribute::TARGET_CLASS)]
readonly class CachePublic extends AbstractCache
{
	public function tokenize(Tokenizer $tokenizer): Tokenizer
	{
		if ($tokenizer->private) {
			return $tokenizer;
		}

		return $tokenizer->with(public: true);
	}
}
