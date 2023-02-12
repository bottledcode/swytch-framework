<?php


namespace Bottledcode\SwytchFramework\CacheControl;

class Builder
{
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
    }

    public static function neverCache(string $tag): Builder
    {
        return new NeverCache([], false, 0, $tag);
    }

    public static function willChange(string $tag): WillChangeBuilder
    {
        return new WillChangeBuilder([], false, 1, $tag);
    }

    public function compareScore(Builder $other): int
    {
        return $this->score <=> $other->score;
    }
}
