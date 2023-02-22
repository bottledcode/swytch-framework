<?php

namespace Bottledcode\SwytchFramework\Template\Traits;

trait RegularPHP {
	public function begin(): void {
		if(method_exists($this, 'html')) {
			ob_start($this->html(...));
			return;
		}
		ob_start();
	}

	public function end(): string {
		return ob_get_clean();
	}
}
