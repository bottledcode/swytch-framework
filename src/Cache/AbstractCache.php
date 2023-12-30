<?php

namespace Bottledcode\SwytchFramework\Cache;

use Bottledcode\SwytchFramework\Cache\Control\Tokenizer;

readonly abstract class AbstractCache
{
	abstract public function tokenize(Tokenizer $tokenizer): Tokenizer;
}
