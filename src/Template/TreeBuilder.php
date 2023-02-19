<?php

namespace Bottledcode\SwytchFramework\Template;

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
				if (!headers_sent()) {
					setcookie(
						"csrf_token",
						$token,
						[
							'samesite' => 'strict',
							'httponly' => true,
							...(($_SERVER['HTTPS'] ?? false) ? ['secure' => true] : []),
							'path' => $formAddress
						]
					);
				}
				$csrfElement = $this->doc->createElement('input');
				$csrfElement->setAttribute('type', 'hidden');
				$csrfElement->setAttribute('name', 'csrf_token');
				$csrfElement->setAttribute('value', $token);
				$current->appendChild($csrfElement);

				// inject the current state of the form and use csrf token to verify/validate
				$stateData = StateSync::serializeState($this->container->get('state_secret'), end(self::$componentStack)->attributes);

				$stateElement = $this->doc->createElement('input');
				$stateElement->setAttribute('type', 'hidden');
				$stateElement->setAttribute('name', 'state_hash');
				$stateElement->setAttribute('value', $stateData['hash']);
				$current->appendChild($stateElement);
				$stateElement = $this->doc->createElement('input');
				$stateElement->setAttribute('type', 'hidden');
				$stateElement->setAttribute('name', 'state');
				$stateElement->setAttribute('value', $stateData['state']);
				$current->appendChild($stateElement);

				// inject the id that will be sent to the server
				$idElement = $this->doc->createElement('input');
				$idElement->setAttribute('type', 'hidden');
				$idElement->setAttribute('name', 'target_id');
				$idElement->setAttribute('value', $this->getNodeAddress());
				$current->appendChild($idElement);
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

			$id = $attributes['id'] ?? $this->getNodeAddress();
			if (!$skipHxProcessing) {
				$current->setAttribute('id', $id);
			}
			unset($attributes['id']);

			$content = $component->compile($attributes);

			if ($content->childElementCount > 0) {
				$current->appendChild($content);
			}

			array_pop(self::$componentStack);
		}
		return $mode;
	}

	private function getNodeAddress(): string
	{
		return implode('-', array_map(static fn($node) => $node->compiledComponent->component, self::$componentStack));
	}

	protected function autoclose($tagName)
	{
		$this->closed = $this->current;
		$this->actuallyClosed = true;
		return parent::autoclose($tagName);
	}
}
