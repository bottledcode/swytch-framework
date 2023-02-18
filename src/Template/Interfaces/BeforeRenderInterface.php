<?php

namespace Bottledcode\SwytchFramework\Template\Interfaces;

interface BeforeRenderInterface
{
	/**
	 * Called before the component is rendered with the raw attributes before any processing.
	 *
	 * @param array<string> $rawAttributes
	 * @return void
	 */
	public function aboutToRender(array $rawAttributes): void;
}
