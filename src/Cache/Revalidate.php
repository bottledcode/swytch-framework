<?php

namespace Bottledcode\SwytchFramework\Cache;

use Bottledcode\SwytchFramework\Cache\Control\Tokenizer;

#[\Attribute(\Attribute::TARGET_CLASS | \Attribute::TARGET_METHOD)]
readonly class Revalidate extends AbstractCache
{
	public function __construct(
		public RevalidationEnum $when = RevalidationEnum::EveryRequest,
		public int|null $staleSeconds = null
	) {
	}

	public function tokenize(Tokenizer $tokenizer): Tokenizer
	{
		if ($tokenizer->noStore) {
			return $tokenizer;
		}

		// automatically remove any immutability
		if ($tokenizer->immutable) {
			$tokenizer = $tokenizer->with(maxAge: null, immutable: false);
		}

		switch ($this->when) {
			case RevalidationEnum::EveryRequest:
				return $tokenizer->with(
					noCache: true,
					mustRevalidate: false,
					proxyRevalidate: false,
					staleWhileRevalidating: null,
					staleIfError: null,
				);
			case RevalidationEnum::WhenStale:
				// we should revalidate on every request
				if ($tokenizer->noCache) {
					return $tokenizer;
				}
				return $tokenizer->with(mustRevalidate: true, staleWhileRevalidating: null, staleIfError: null);
			case RevalidationEnum::WhenStaleProxies:
				if ($tokenizer->noCache) {
					return $tokenizer;
				}
				return $tokenizer->with(proxyRevalidate: true);
			case RevalidationEnum::AfterStale:
				if ($tokenizer->noCache || $tokenizer->mustRevalidate || $tokenizer->proxyRevalidate) {
					return $tokenizer;
				}
				return $tokenizer->with(staleWhileRevalidating: $this->staleSeconds);
			case RevalidationEnum::AfterError:
				if ($tokenizer->noCache || $tokenizer->mustRevalidate || $tokenizer->proxyRevalidate) {
					return $tokenizer;
				}
				return $tokenizer->with(staleIfError: $this->staleSeconds);
			default:
				throw new \LogicException('Unknown enum type!');
		}
	}
}
