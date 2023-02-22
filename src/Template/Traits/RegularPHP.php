<?php

namespace Bottledcode\SwytchFramework\Template\Traits;

trait RegularPHP {
	private function begin(): void {
		ob_start();
	}

	private function end(): string {
		return ob_get_clean();
	}
}
