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
			$current = ($selfClosing && $this->actuallyClosed) ? $this->closed : $this->current;

			// we need to remove the attributes from the component
			foreach($attributes as $key => $value) {
				$current->removeAttribute($key);
			}

			$component = new CompiledComponent($this->components[$name], $this->container, $this->compiler);
			$content = $component->compile($attributes);

			if($content->childElementCount > 0) {
				$current->appendChild($content);
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
