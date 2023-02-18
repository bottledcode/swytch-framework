<?php

namespace Bottledcode\SwytchFramework\Template\Functional;

class Callback {
	public function __construct(private \Closure $callback) {
		// todo: calculate return path
	}
}
