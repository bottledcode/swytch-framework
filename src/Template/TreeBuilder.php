<?php

namespace Bottledcode\SwytchFramework\Template;

use JetBrains\PhpStorm\ArrayShape;
use Masterminds\HTML5\Parser\DOMTreeBuilder;
use Psr\Container\ContainerInterface;

class TreeBuilder extends DOMTreeBuilder
{
	/**
	 * @var array<RenderedComponent> The stack of components that are currently being rendered.
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

	private function getNodeAddress(): string {
		return implode('-', array_map(static fn($node) => $node->compiledComponent->component, self::$componentStack));
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

		if ($name === 'form') {
			$formAddress = $attributes['hx-post'] ?? $attributes['hx-put'] ?? $attributes['hx-delete'] ?? $attributes['hx-patch'] ?? null;
			if ($formAddress !== null) {
				// inject csrf token
				$token = base64_encode(random_bytes(32));
				setcookie(
					"csrf_token",
					$token,
					['samesite' => 'strict', 'httponly' => true, ...(($_SERVER['HTTPS'] ?? false) ? ['secure' => true] : []), 'path' => $formAddress]
				);
				$csrfElement = $current->createElementNS('', 'input');
				$csrfElement->setAttribute('type', 'hidden');
				$csrfElement->setAttribute('name', 'csrf_token');
				$csrfElement->setAttribute('value', $token);
				$current->appendChild($csrfElement);

				// inject the current state of the form and use csrf token to verify/validate
				$stateElement = $current->createElementNS('', 'input');
				$stateElement->setAttribute('type', 'hidden');
				$stateElement->setAttribute('name', 'state_hash');
				$state = end(self::$componentStack);
				$state = json_encode($state->attributes, JSON_THROW_ON_ERROR);
				$state = hash_hmac('sha256', $state, $this->container->get('state_secret'));
				$stateElement->setAttribute('value', $state);
				$current->appendChild($stateElement);
				$stateElement = $current->createElementNS('', 'input');
				$stateElement->setAttribute('type', 'hidden');
				$stateElement->setAttribute('name', 'state');
				$stateElement->setAttribute('value', base64_encode($state));
				$current->appendChild($stateElement);
			}
		}

		if (array_key_exists($name, $this->components)) {
			// we need to remove the attributes from the component
			foreach ($attributes as $key => $value) {
				$current->removeAttribute($key);
			}

			$skipHxProcessing = false;
			if (method_exists($this->components[$name], 'skipHxProcessing')) {
				$skipHxProcessing = ($this->components[$name])::skipHxProcessing();
			}

			$component = new CompiledComponent($this->components[$name], $this->container, $this->compiler);

			self::$componentStack[] = new RenderedComponent($component, $attributes);

			if (!$skipHxProcessing) {
				$current->setAttribute('id', $this->getNodeAddress());
			}

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
