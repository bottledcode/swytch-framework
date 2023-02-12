<?php

namespace Bottledcode\SwytchFramework\CacheControl;

class Queue
{
    /** @var Builder[] */
    public array $queue = [];

    public function enqueue(Builder $builder): void
    {
        $this->queue[] = $builder;
    }
}
