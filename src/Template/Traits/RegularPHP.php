<?php

namespace Bottledcode\SwytchFramework\Template\Traits;

trait RegularPHP
{

	/**
	 * Start output buffering
	 *
	 * @return void
	 */
	private function begin(): void
	{
		ob_start();
	}

	/**
	 * Stop output buffering and return the result
	 * @return string Everything rendered since begin() was called
	 */
	private function end(): string
	{
		return ob_get_clean();
	}
}
