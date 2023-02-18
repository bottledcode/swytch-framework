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
		$current = ($selfClosing && $this->actuallyClosed) ? $this->closed : $this->current;
		if($name === 'input') {
			// get the last input, hopefully.
			// todo: make this better
			$current = $this->current->lastChild;
		}

		foreach($attributes as $key => $value) {
			if(is_string($value) && str_starts_with($value, '{__TRIGGER__')) {
				// we need to adjust the attributes
				$current->removeAttribute($key);
				$triggerValue = str_replace(['{__TRIGGER__ ', '}'], '', $value);
				$triggerType = str_replace('on', '', $key);
				if(str_starts_with($key, 'onkey')) {
					$current->setAttribute('hx-trigger', "$triggerType changed delay:500ms");
				}
				$current->setAttribute('id', uniqid(more_entropy: true));
			}
		}

		if (array_key_exists($name, $this->components)) {
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
