<?php

namespace Bottledcode\SwytchFramework\Template;

function dangerous(string $html): string {
	static $boundary = null;
	if($boundary === null) {
		$boundary = random_bytes(16);
	}

	return $boundary . $html . $boundary;
}
