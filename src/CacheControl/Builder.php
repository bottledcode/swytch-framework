<?php


namespace Bottledcode\SwytchFramework\CacheControl;

use Closure;

class Builder
{
	protected static Closure $header;

	/**
	 * @param array<array-key, string> $values
	 * @param bool $etagRequired
	 * @param int $score
	 * @param string $tag
	 */
	protected function __construct(
		protected array $values,
		public bool $etagRequired,
		protected int $score,
		public string $tag
	) {
		if (!isset(self::$header)) {
			self::$header = header(...);
		}
	}

	public static function neverCache(string $tag): self
	{
		return new NeverCache([], false, 0, $tag);
	}

	public static function willChange(string $tag): WillChangeBuilder
	{
		return new WillChangeBuilder([], false, 1, $tag);
	}

	public static function setHeaderFunc(Closure $func): void
	{
		self::$header = $func;
	}

	public function compareScore(self $other): int
	{
		return $this->score <=> $other->score;
	}
}
