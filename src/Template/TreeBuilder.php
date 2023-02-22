<?php

namespace Bottledcode\SwytchFramework\Template;

use Bottledcode\SwytchFramework\Template\Interfaces\EscaperInterface;
use Bottledcode\SwytchFramework\Template\Interfaces\StateProviderInterface;
use Laminas\Escaper\Escaper;
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

	private readonly StateProviderInterface $stateProvider;

	public function __construct(
		bool $isFragment,
		array $options,
		private array $components,
		private Compiler $compiler,
		private ContainerInterface $container,
	) {
		parent::__construct($isFragment, $options);
		$this->stateProvider = $this->container->get(StateProviderInterface::class);
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

		/**
		 * @var Escaper $escaper
		 */
		$escaper = $this->container->get(Escaper::class);

		if ($name === 'form') {
			$formAddress = $attributes['hx-post'] ?? $attributes['hx-put'] ?? $attributes['hx-delete'] ?? $attributes['hx-patch'] ?? '';
			preg_match_all(Output::ESCAPE_SEQUENCE, $formAddress, $matches);
			foreach ($matches[1] as $match) {
				$match = trim($match, '{}');
				$formAddress = str_replace("{{$match}}", $escaper->escapeUrl($match), $formAddress);
			}
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
				$stateData = $this->stateProvider->serializeState(end(self::$componentStack)->attributes);

				$stateElement = $this->doc->createElement('input');
				$stateElement->setAttribute('type', 'hidden');
				$stateElement->setAttribute('name', 'state_hash');
				$stateElement->setAttribute('value', $this->stateProvider->signState($stateData));
				$current->appendChild($stateElement);

				// todo: recreate refs
				$stateElement = $this->doc->createElement('input');
				$stateElement->setAttribute('type', 'hidden');
				$stateElement->setAttribute('name', 'state');
				$stateElement->setAttribute('value', $stateData);
				$current->appendChild($stateElement);

				// inject the id that will be sent to the server
				$idElement = $this->doc->createElement('input');
				$idElement->setAttribute('type', 'hidden');
				$idElement->setAttribute('name', 'target_id');
				$idElement->setAttribute('value', end(self::$componentStack)->id ?? $this->getNodeAddress());
				$current->appendChild($idElement);
			}
		}

		if (array_key_exists($name, $this->components)) {
			$skipHxProcessing = false;
			if (method_exists($this->components[$name], 'skipHxProcessing')) {
				$skipHxProcessing = ($this->components[$name])::skipHxProcessing();
			}

			$component = new CompiledComponent($this->components[$name], $this->container, $this->compiler);

			$id = $attributes['id'] ?? 'id' . substr(md5(random_bytes(8) . time()), 0, 6);

			self::$componentStack[] = new RenderedComponent($component, $attributes, $id);

			if (!$skipHxProcessing) {
				$current->setAttribute('id', $id);
			}
			unset($attributes['id']);
			$usedAttributes = $component->getUsedAttributes();

			// we need to remove the attributes from the component
			foreach (array_intersect_key($attributes, $usedAttributes) as $key => $value) {
				$current->removeAttribute($key);
			}

			$blobber = $this->container->get(EscaperInterface::class);
			$passedAttributes = array_intersect_key($attributes, $usedAttributes);
			$passedAttributes = array_map(fn($value) => $blobber->replaceBlobs($value, $escaper->escapeHtmlAttr(...)), $passedAttributes);

			$content = $component->compile($passedAttributes);

			if ($content->childElementCount > 0) {
				$current->appendChild($content);
			}

			array_pop(self::$componentStack);
		}
		return $mode;
	}

	private function getNodeAddress(): string
	{
		return implode(
			'-',
			array_map(static fn($node) => str_replace('\\', '_', $node->compiledComponent->component),
				self::$componentStack)
		);
	}

	protected function autoclose($tagName)
	{
		$this->closed = $this->current;
		$this->actuallyClosed = true;
		return parent::autoclose($tagName);
	}
}
