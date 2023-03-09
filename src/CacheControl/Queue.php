<?php

namespace Bottledcode\SwytchFramework\CacheControl;

class Queue
{
	/** @var Builder[] */
	private array $queue = [];

	public function enqueue(Builder $builder): void
	{
		$this->queue[] = $builder;
	}

	/**
	 * @return Builder[]
	 */
	public function getSortedQueue(): array
	{
		usort($this->queue, static fn($a, $b) => $a->compareScore($b));
		return $this->queue;
	}
}
