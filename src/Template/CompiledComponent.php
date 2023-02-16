<?php

namespace Bottledcode\SwytchFramework\Template;

readonly class CompiledComponent implements \Stringable
{
	/**
	 * @param array<array-key, array<array-key, string>> $fragmentMap
	 * @param class-string $component
	 */
	public function __construct(public array $fragmentMap, public string $component)
	{
	}

	public function __toString(): string
	{
		return '';
	}
}
