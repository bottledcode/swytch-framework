<?php

namespace Bottledcode\SwytchFramework\Cache;

use Bottledcode\SwytchFramework\Cache\Control\Tokenizer;

/**
 * Sets the cache to "private"
 */
#[\Attribute(\Attribute::TARGET_METHOD | \Attribute::TARGET_CLASS)]
readonly class UserSpecific extends AbstractCache
{
	public function tokenize(Tokenizer $tokenizer): Tokenizer
	{
		return $tokenizer->with(public: false, private: true);
	}
}
