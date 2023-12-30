<?php

namespace Bottledcode\SwytchFramework\Cache\Control;

readonly class Tokenizer
{
	public function __construct(
		/**
		 * @var int|null Indicates that caches can store this response and reuse it for subsequent requests while it's fresh.
		 */
		public int|null $maxAge = null,

		/**
		 * @var int|null indicates how long the response remains fresh in a shared cache.
		 */
		public int|null $sMaxAge = null,

		/**
		 * @var bool indicates that the response can be stored in caches, but the response must be validated with the origin
		 * server before each reuse, even when the cache is disconnected from the origin server.
		 */
		public bool $noCache = false,

		/**
		 * @var bool indicates that the response can be stored in caches and can be reused while fresh. If the response
		 * becomes stale, it must be validated with the origin server before reuse.
		 */
		public bool $mustRevalidate = false,

		/**
		 * @var bool same as must-revalidate, but for proxies
		 */
		public bool $proxyRevalidate = false,

		/**
		 * @var bool indicates that any caches of any kind (public or shared) should not store this response.
		 */
		public bool $noStore = false,

		/**
		 * @var bool indicates that any caches of any kind (public or shared) should not store this response.
		 */
		public bool $public = true,

		/**
		 * @var bool indicates that the response will not be updated while it's fresh.
		 */
		public bool $immutable = false,

		/**
		 * @var int|null indicates that the cache could reuse a stale response while it revalidates it to a cache.
		 */
		public int|null $staleWhileRevalidating = null,

		/**
		 * @var int|null indicates that the cache can reuse a stale response when an upstream server generates an error, or
		 * when the error is generated locally
		 */
		public int|null $staleIfError = null,
	) {
	}

	public function with(
		int|null|false $maxAge = false,
		int|null|false $sMaxAge = false,
		bool|null $noCache = null,
		bool|null $mustRevalidate = null,
		bool|null $proxyRevalidate = null,
		bool|null $noStore = null,
		bool|null $public = null,
		bool|null $immutable = null,
		int|null|false $staleWhileRevalidating = false,
		int|null|false $staleIfError = false,
	): self {
		return new self(
			maxAge: $this->withInt($this->maxAge, $maxAge),
			sMaxAge: $this->withInt($this->sMaxAge, $sMaxAge),
			noCache: $this->withBool($this->noCache, $noCache),
			mustRevalidate: $this->withBool($this->mustRevalidate, $mustRevalidate),
			proxyRevalidate: $this->withBool($this->proxyRevalidate, $proxyRevalidate),
			noStore: $this->withBool($this->noStore, $noStore),
			public: $this->withBool($this->public, $public),
			immutable: $this->withBool($this->immutable, $immutable),
			staleWhileRevalidating: $this->withInt($this->staleWhileRevalidating, $staleWhileRevalidating),
			staleIfError: $this->withInt($this->staleIfError, $staleIfError),
		);
	}

	private function withInt(int|null $original, int|null|false $var): int|null
	{
		return $var === false ? $original : $var;
	}

	private function withBool(bool $original, bool|null $var): bool
	{
		return $var ?? $original;
	}

	public function render(): string
	{
		$header = [
			$this->public ? 'public' : 'private',
			...$this->header($this->maxAge, "max-age=$this->maxAge"),
			...$this->header($this->sMaxAge, "s-maxage=$this->sMaxAge"),
			...$this->header($this->noCache, "no-cache"),
			...$this->header($this->mustRevalidate, "must-revalidate"),
			...$this->header($this->proxyRevalidate, "proxy-revalidate"),
			...$this->header($this->noStore, "no-store"),
			...$this->header($this->immutable, "immutable"),
			...$this->header($this->staleWhileRevalidating, "stale-while-revalidate=$this->staleWhileRevalidating"),
			...$this->header($this->staleIfError, "stale-if-error=$this->staleIfError"),
		];

		return implode(' ', $header);
	}

	private function header($prop, $value): array
	{
		return $prop ? [$value] : [];
	}
}
