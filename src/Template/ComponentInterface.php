<?php

namespace Bottledcode\SwytchFramework\Template;

interface ComponentInterface
{
	public function aboutToRender(): void;

	/**
	 * @param array<array-key, string> $props
	 * @return string
	 */
	public function render(array $props): string;
}
