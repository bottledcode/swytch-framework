<?php

namespace Bottledcode\SwytchFramework\Template;

function dangerous(string $html): string {
	static $boundary = null;
	if($boundary === null) {
		$boundary = "\0";
	}

	return $boundary . $html . $boundary;
}
