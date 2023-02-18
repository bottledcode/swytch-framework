<?php

namespace Bottledcode\SwytchFramework\Template;

use Bottledcode\SwytchFramework\Template\Interfaces\HxInterface;
use Masterminds\HTML5\Parser\DOMTreeBuilder;
use Psr\Container\ContainerInterface;

class TreeBuilder extends DOMTreeBuilder
{
	/**
	 * @var array<string> The stack of components that are currently being rendered.
	 */
	protected static array $componentStack = [];
	protected \DOMElement|\DOMDocumentFragment $closed;
	protected bool $actuallyClosed = false;

	public function __construct(
		bool $isFragment,
		array $options,
		private array $components,
		private Compiler $compiler,
		private ContainerInterface $container
	) {
		parent::__construct($isFragment, $options);
	}

	public function startTag($name, $attributes = array(), $selfClosing = false)
	{
		$this->actuallyClosed = false;
		$mode = parent::startTag($name, $attributes, $selfClosing);
		$current = ($selfClosing && $this->actuallyClosed) ? $this->closed : $this->current;
		if ($name === 'input') {
			// get the last input, hopefully.
			// todo: make this better
			$current = $this->current->lastChild;
		}

		foreach ($attributes as $key => $value) {
			if (is_string($value) && str_starts_with($value, '{__TRIGGER__')) {
				// we need to adjust the attributes
				$current->removeAttribute($key);
				$triggerValue = str_replace(['{__TRIGGER__ ', '}'], '', $value);
				$triggerType = str_replace('on', '', $key);
				if (str_starts_with($key, 'onkey')) {
					$current->setAttribute('hx-trigger', "$triggerType changed delay:500ms");
				}
				// calculate a stable id for the element
				$xpath = $current->getNodePath();
				$component = end(self::$componentStack) ?: 'top';
				$current->setAttribute('id', $component . $name . $xpath);
			}
		}

		if (array_key_exists($name, $this->components)) {
			// we need to remove the attributes from the component
			foreach ($attributes as $key => $value) {
				$current->removeAttribute($key);
			}

			self::$componentStack[] = $name;
			$skipHxProcessing = false;
			if (method_exists($this->components[$name], 'skipHxProcessing')) {
				$skipHxProcessing = ($this->components[$name])::skipHxProcessing();
			}
			if (!$skipHxProcessing) {
				$current->setAttribute('id', $name . $current->getNodePath());
				$current->setAttribute('hx-swap', 'innerHTML');
				$current->setAttribute('hx-post', '/component/' . $name);
			}

			$component = new CompiledComponent($this->components[$name], $this->container, $this->compiler);
			$content = $component->compile($attributes);

			if ($content->childElementCount > 0) {
				$current->appendChild($content);
			}

			array_pop(self::$componentStack);
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
