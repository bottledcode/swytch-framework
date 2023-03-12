<?php

namespace Bottledcode\SwytchFramework\Template\Functional;

interface DataProvider
{
	/**
	 * @return array<string, mixed>
	 */
	public function provideAttributes(): array;

	public function provideValues(string $value): mixed;
}
