<?php

namespace Bottledcode\SwytchFramework\Template;

use Masterminds\HTML5\Parser\DOMTreeBuilder;

class TreeBuilder extends DOMTreeBuilder
{
	protected \DOMElement|\DOMDocumentFragment $closed;
	protected bool $actuallyClosed = false;

	public function __construct(bool $isFragment, array $options, private array $components, private Compiler $compiler)
	{
		parent::__construct($isFragment, $options);
	}

	public function startTag($name, $attributes = array(), $selfClosing = false)
	{
		$this->actuallyClosed = false;
		$mode = parent::startTag($name, $attributes, $selfClosing);
		if (array_key_exists($name, $this->components)) {
			// we need a rendered component here
			$map = $this->compiler->parseFragment($component = $this->components[$name]);
			$map = var_export($map, true);
			$c = CompiledComponent::class;
			$component = json_encode($component);
			$content = "<?= new $c($map, $component) ?>";

			$node = $this->doc->createTextNode($content);

			if($selfClosing && $this->actuallyClosed) {
				$this->closed->appendChild($node);
			} else {
				$this->current->appendChild($node);
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
