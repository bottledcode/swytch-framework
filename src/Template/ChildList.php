<?php

namespace Bottledcode\SwytchFramework\Template;

use DOMElement;
use DOMNodeList;

class ChildList
{
	/**
	 * @param DOMElement[] $children
	 */
	public function __construct(public array $children)
	{
	}

	/**
	 * @param DOMNodeList<DOMElement> $newChildren
	 * @return void
	 */
	public function replaceWith(DOMNodeList $newChildren): void
	{
		$asArr = iterator_to_array($newChildren);
		foreach ($this->children as $child) {
			$cloned = array_map(fn($x) => $x->cloneNode(true), $asArr);
			$child->replaceWith(...$cloned);
		}
	}
}
