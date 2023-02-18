<?php

namespace Bottledcode\SwytchFramework\Template;

use Masterminds\HTML5\Parser\DOMTreeBuilder;
use Psr\Container\ContainerInterface;

class TreeBuilder extends DOMTreeBuilder
{
	protected \DOMElement|\DOMDocumentFragment $closed;
	protected bool $actuallyClosed = false;

	public function __construct(bool $isFragment, array $options, private array $components, private Compiler $compiler, private ContainerInterface $container)
	{
		parent::__construct($isFragment, $options);
	}

	public function startTag($name, $attributes = array(), $selfClosing = false)
	{
		$this->actuallyClosed = false;
		$mode = parent::startTag($name, $attributes, $selfClosing);
		if (array_key_exists($name, $this->components)) {
			// we need to remove the attributes from the component
			if($selfClosing && $this->actuallyClosed) {
				foreach($attributes as $key => $value) {
					$this->closed->removeAttribute($key);
				}
			} else {
				foreach($attributes as $key => $value) {
					$this->current->removeAttribute($key);
				}
			}

			$component = new CompiledComponent($this->components[$name], $this->container, $this->compiler);
			$content = $component->compile($attributes);

			if($selfClosing && $this->actuallyClosed) {
				$this->closed->appendChild($content);
			} else {
				$this->current->appendChild($content);
			}
		}
		return $mode;
	}

	protected function autoclose($tagName)
	{
		$this->closed = $this->current;
		$this->actuallyClosed = true;
		return parent::autoclose($tagName);
	}
}
