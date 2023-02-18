<?php

namespace Bottledcode\SwytchFramework\Template\Traits;

trait FancyClasses
{
	/**
	 * Given an array of classes, return a string of classes
	 * @param array $classes
	 * @return string
	 */
	private function classNames(array $classes): string
	{
		return implode(' ', $classes);
	}
}
