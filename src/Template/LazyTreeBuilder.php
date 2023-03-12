<?php

namespace Bottledcode\SwytchFramework\Template;

use Bottledcode\SwytchFramework\Template\Attributes\Authenticated;
use Bottledcode\SwytchFramework\Template\Attributes\Authorized;
use Bottledcode\SwytchFramework\Template\Functional\DataProvider;
use Bottledcode\SwytchFramework\Template\Interfaces\AuthenticationServiceInterface;
use Bottledcode\SwytchFramework\Template\Interfaces\EscaperInterface;
use Bottledcode\SwytchFramework\Template\Interfaces\StateProviderInterface;
use Closure;
use DI\FactoryInterface;
use DOMDocumentFragment;
use DOMElement;
use DOMNode;
use Laminas\Escaper\Escaper;
use LogicException;
use Masterminds\HTML5\Parser\DOMTreeBuilder;
use olvlvl\ComposerAttributeCollector\Attributes;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\ContainerInterface;
use Psr\Container\NotFoundExceptionInterface;
use Psr\Log\LoggerInterface;
use RuntimeException;
use Throwable;

class LazyTreeBuilder extends DOMTreeBuilder
{
	private static int $id = 0;

	/**
	 * @var array<CompiledComponent>
	 */
	private array $componentStack = [];

	private readonly StateProviderInterface $stateProvider;
	private readonly AuthenticationServiceInterface|null $authenticationService;
	/**
	 * @var array<DOMNode>
	 */
	private array $childParents = [];

	/**
	 * @var array<array<CompiledComponent>>
	 */
	private array $delayStack = [];

	/**
	 * @var array<CompiledComponent>
	 */
	private array|null $delayStackPointer = null;

	/**
	 * @var array<DataProvider>
	 */
	private array $dataProviders = [];

	/**
	 * @param ContainerInterface $container
	 * @param array<string, class-string> $components
	 * @param LoggerInterface $logger
	 * @param bool $isFragment
	 * @param array<mixed> $options
	 * @param Closure|null $children
	 * @throws ContainerExceptionInterface
	 * @throws NotFoundExceptionInterface
	 */
	public function __construct(
		private readonly ContainerInterface $container,
		private readonly array $components,
		private readonly LoggerInterface $logger,
		bool $isFragment = false,
		array $options = [],
		private readonly Closure|null $children = null,
		private readonly CompiledComponent|null $parentComponent = null,
	) {
		parent::__construct($isFragment, $options);
		$this->stateProvider = $this->container->get(StateProviderInterface::class);
		try {
			$this->authenticationService = $this->container->get(AuthenticationServiceInterface::class);
		} catch (NotFoundExceptionInterface|ContainerExceptionInterface) {
			$this->authenticationService = null;
		}
	}

	/**
	 * @param string $msg
	 * @param int $line
	 * @param int $col
	 * @return void
	 */
	public function parseError($msg, $line = 0, $col = 0): void
	{
		$this->logger->notice('HTML parse error: ' . $msg, compact('line', 'col'));
		parent::parseError($msg, $line, $col);
	}

	/**
	 * @param string $name
	 * @param array<string> $attributes
	 * @param bool $selfClosing
	 * @return int
	 */
	public function startTag($name, $attributes = array(), $selfClosing = false): int
	{
		if ($selfClosing) {
			$this->pushComponent($name, $attributes);
		}

		$tag = parent::startTag($name, $attributes, $selfClosing);

		$this->decorateForm($name, $attributes);

		if (!$selfClosing) {
			$this->pushComponent($name, $attributes);
		}

		return $tag;
	}

	/**
	 * @param string $name
	 * @param array<string> $attributes
	 * @return void
	 * @throws ContainerExceptionInterface
	 * @throws NotFoundExceptionInterface
	 */
	private function pushComponent(string $name, array $attributes): void
	{
		$component = $this->components[$name] ?? null;
		if ($component) {
			$this->componentStack[$name] = new CompiledComponent(
				$component,
				$this->container->get(FactoryInterface::class),
				$this->container->get(Compiler::class),
				$attributes
			);

			// in a weird quirk of php, this results in appending a new array to the delay stack and they are the same variable.
			$this->delayStackPointer = &$this->delayStack[];
			$this->delayStackPointer = [];

			// consume children
			$this->childParents[] = $this->current;
			$this->current = $this->document()->createDocumentFragment();

			// fragment must be attached to the document in order to be kept. See https://bugs.php.net/bug.php?id=39593
			$this->document()->append($this->current);
		}
	}

	/**
	 * @param string $name
	 * @return void
	 */
	public function endTag($name): void
	{
		parent::endTag($name);
	}

	public function autoclose($tagName): bool
	{
		return $this->render($tagName);
	}

	private function decorateForm(string $name, array $attributes): void {
		/**
		 * @var Escaper $escaper
		 */
		$escaper = $this->container->get(Escaper::class);

		if($name === 'form') {
			$formAddress = $attributes['hx-post'] ?? $attributes['hx-put'] ?? $attributes['hx-delete'] ?? $attributes['hx-patch'] ?? '';
			/** @var EscaperInterface $blobber */
			$blobber = $this->container->get(EscaperInterface::class);
			$formAddress = $blobber->replaceBlobs($formAddress, $escaper->escapeUrl(...));
			if (!empty($formAddress)) {
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
				$this->current->appendChild($csrfElement);

				// inject the current state of the form and use csrf token to verify/validate
				$component = end($this->componentStack) ?: $this->parentComponent;
				$stateData = $this->stateProvider->serializeState($component->attributes);

				$stateElement = $this->doc->createElement('input');
				$stateElement->setAttribute('type', 'hidden');
				$stateElement->setAttribute('name', 'state_hash');
				$stateElement->setAttribute('value', $this->stateProvider->signState($stateData));
				$this->current->appendChild($stateElement);

				// todo: recreate refs
				$stateElement = $this->doc->createElement('input');
				$stateElement->setAttribute('type', 'hidden');
				$stateElement->setAttribute('name', 'state');
				$stateElement->setAttribute('value', $stateData);
				$this->current->appendChild($stateElement);

				// inject the id that will be sent to the server
				$idElement = $this->doc->createElement('input');
				$idElement->setAttribute('type', 'hidden');
				$idElement->setAttribute('name', 'target_id');
				$idElement->setAttribute('value', $this->calculateId(self::$id));
				$this->current->appendChild($idElement);
			}
		}
	}

	private function shouldRender(string $name): bool {
		$classAttr = Attributes::forClass($this->components[$name]);
		foreach ($classAttr->classAttributes as $attr) {
			if ($attr instanceof Authenticated) {
				$userAuthenticated = $this->authenticationService->isAuthenticated();
				switch ([$userAuthenticated, $attr->visible]) {
					// set to visible and user is authenticated
					case [true, true]:
					case [false, false]:
						break;
					case [false, true]: // set to visible and user is not authenticated
					case [true, false]: // set to not visible and user is not authenticated
						return false;
				}
			}
			if ($attr instanceof Authorized) {
				$userAuthorized = $this->authenticationService->isAuthorizedVia(...$attr->roles);
				switch ([$userAuthorized, $attr->visible]) {
					case [true, true]:
					case [false, false]:
						break;
					case [false, true]:
					case [true, false]:
						return false;
				}
			}
		}
		return true;
	}

	private function render(string $name): bool
	{
		$component = end($this->componentStack) ?: null;
		if ($component && key($this->componentStack) === $name && $this->shouldRender($name)) {
			$children = $this->current instanceof DOMDocumentFragment ? iterator_to_array(
				$this->current->childNodes
			) : [];
			$this->current = array_pop($this->childParents) ?: throw new LogicException('No parent to render to');

			if (count($this->componentStack) === 1) {
				// we are the parent component and should render ourselves

				$this->decorateComponent(end($this->componentStack));
				$renderedChildren = false;
				$children = $this->extractChildrenCallable($children, $renderedChildren);

				try {
					$compiledDom = $component->compile(
						$consumableAttr = $component->renderAttributes(),
						$children,
						$this->dataProviders
					);
					if ($component->renderedComponent instanceof DataProvider) {
						$this->dataProviders[] = $component->renderedComponent;
					}
				} catch (Throwable $e) {
					throw new RuntimeException('Error compiling component ' . $component->component, 0, $e);
				}
				$this->attachToDom($compiledDom, $consumableAttr);
				$this->renderChildren($renderedChildren);

				if (is_subclass_of($component->component, DataProvider::class)) {
					array_pop($this->dataProviders);
				}
			} else {
				$this->delayStackPointer[] = [$component, $children, $this->current];
			}

			array_pop($this->componentStack);
		}

		if ($this->replaceChildren($name)) {
			return true;
		}

		return parent::autoclose($name);
	}

	private function decorateComponent(CompiledComponent|false $component): void
	{
		if($component === false) {
			return;
		}

		if (method_exists($component->component, 'skipHxProcessing')) {
			$skipHxProcessing = [$component->component, 'skipHxProcessing'];
			if ($skipHxProcessing()) {
				return;
			}
		}
		$id = $this->calculateId(++self::$id);
		if ($this->current instanceof DOMElement && !$this->current->hasAttribute('id')) {
			$this->current->setAttribute('id', $id);
		}
	}

	private function calculateId(int $id): string {
		return substr(md5((string)$id), 0, 8);
	}

	/**
	 * @param array<DOMNode> $children
	 * @param false $renderedChildren
	 * @return callable|null
	 */
	public function extractChildrenCallable(array $children, bool &$renderedChildren): callable|null
	{
		if (empty($children)) {
			return null;
		}
		return function () use (&$renderedChildren, $children) {
			$renderedChildren = true;
			foreach ($children as $child) {
				$this->current->parentNode->appendChild($child);
			}
			$parent = $this->current->parentNode;
			$parent->removeChild($this->current);
			$this->current = $parent;
			return true;
		};
	}

	/**
	 * @param DOMDocumentFragment $compiledDom
	 * @param array<string> $consumableAttr
	 * @return void
	 */
	public function attachToDom(DOMDocumentFragment $compiledDom, array $consumableAttr): void
	{
		if ($compiledDom->childElementCount > 0) {
			$this->current->appendChild($compiledDom);
		}
		if ($this->current instanceof DOMElement) {
			foreach ($consumableAttr as $attr => $value) {
				$this->current->removeAttribute($attr);
			}
		}
	}

	/**
	 * @param bool $renderedChildren
	 * @return void
	 */
	public function renderChildren(bool $renderedChildren): void
	{
		while (count($this->delayStackPointer) && $renderedChildren) {
			// store the previous state
			$toRender = array_pop($this->delayStackPointer);
			$actualCurrent = $this->current;
			$previousCurrent = $toRender[2];
			$previousStack = $this->componentStack;
			$this->componentStack = [$previousCurrent->tagName => $toRender[0]];
			$this->childParents[] = $toRender[2];
			$this->current = $previousCurrent;

			// now render the state
			$this->render($previousCurrent->tagName);

			// restore state
			$this->current = $actualCurrent;
			$this->componentStack = $previousStack;
		}
	}

	private function replaceChildren(string $name): true|null
	{
		if ($name === 'children' && is_callable($this->children)) {
			($this->children)->call($this);
			return true;
		}
		return null;
	}
}
