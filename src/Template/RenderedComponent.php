<?php

namespace Bottledcode\SwytchFramework\Template;

readonly class RenderedComponent
{

	/**
	 * @param CompiledComponent $compiledComponent
	 * @param array<string> $attributes
	 */
	public function __construct(public CompiledComponent $compiledComponent, public array $attributes, public string|null $id = null)
	{
	}
}
