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

	public function getSortedQueue(): array {
		usort($this->queue, fn($a, $b) => $a->compareScore($b));
		return $this->queue;
	}
}
