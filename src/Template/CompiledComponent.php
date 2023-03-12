<?php

namespace Bottledcode\SwytchFramework\Template;

use Bottledcode\SwytchFramework\Template\Escapers\Variables;
use Bottledcode\SwytchFramework\Template\Functional\DataProvider;
use Bottledcode\SwytchFramework\Template\Interfaces\BeforeRenderInterface;
use Bottledcode\SwytchFramework\Template\Interfaces\EscaperInterface;
use DI\DependencyException;
use DI\FactoryInterface;
use DI\NotFoundException;
use DOMDocument;
use DOMDocumentFragment;
use Masterminds\HTML5\Exception;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\ContainerInterface;
use Psr\Container\NotFoundExceptionInterface;
use ReflectionClass;
use ReflectionException;
use RuntimeException;

readonly class CompiledComponent
{
	public mixed $renderedComponent;

	/**
	 * @param class-string $component
	 * @param FactoryInterface&ContainerInterface $container
	 * @param Compiler $compiler
	 * @param array<string> $attributes
	 */
	public function __construct(
		public string $component,
		private FactoryInterface $container,
		private Compiler $compiler,
		public array $attributes = []
	) {
	}

	/**
	 * @return string
	 * @throws ContainerExceptionInterface
	 * @throws NotFoundExceptionInterface
	 */
	public function renderToString(): string
	{
		$dom = $this->compile();
		return $this->compiler->renderCompiledHtml($dom);
	}

	/**
	 * @param array<string, string> $attributes
	 * @param callable|null $children
	 * @param array<DataProvider> $dataProviders
	 * @return DOMDocument|DOMDocumentFragment
	 * @throws ContainerExceptionInterface
	 * @throws DependencyException
	 * @throws Exception
	 * @throws NotFoundException
	 * @throws NotFoundExceptionInterface
	 */
	public function compile(
		array $attributes = [],
		callable|null $children = null,
		array $dataProviders = []
	): DOMDocument|DOMDocumentFragment {
		// we are about to render
		$component = $this->container->make($this->component);
		if ($component instanceof BeforeRenderInterface) {
			$component->aboutToRender($attributes);
		}

		$dataProviders = array_filter($dataProviders, fn($x) => !($x instanceof $this));

		if (!empty($dataProviders)) {
			$newAttributes = array_replace_recursive(
				...array_map(static fn(DataProvider $x) => $x->provideAttributes(), $dataProviders)
			);
			$attributes = array_replace_recursive($attributes, $newAttributes);
			foreach ($dataProviders as $dataProvider) {
				foreach ($attributes as &$value) {
					$value = $dataProvider->provideValues($value);
				}
			}
		}

		// render the component
		$rendered = $component->render(...$attributes);

		$this->renderedComponent = $component;

		return $this->compiler->compile($rendered, $children, $this);
	}

	public function renderAttributes(): array
	{
		$usedAttributes = $this->getUsedAttributes();
		$blobber = $this->container->get(EscaperInterface::class);
		// find parameters we are passing to the component
		$passedAttributes = array_intersect_key($this->attributes, array_change_key_case($usedAttributes));

		// get the correctly cased names
		$nameMap = array_combine(array_keys(array_change_key_case($usedAttributes)), array_keys($usedAttributes));
		$passedAttributes = array_combine(
			array_map(static fn($key) => $nameMap[$key], array_keys($passedAttributes)),
			$passedAttributes
		);

		// replace attributes with real values
		$passedAttributes = array_map(
			static fn(string|null $value) => $blobber->replaceBlobs($value ?? true, Variables::escape(...)),
			$passedAttributes
		);

		return $passedAttributes;
	}

	/**
	 * @return array<string, int>
	 */
	public function getUsedAttributes(): array
	{
		$attributes = [];
		try {
			$classReflection = new ReflectionClass($this->component);
			$methodReflection = $classReflection->getMethod('render');
			$parameterReflection = $methodReflection->getParameters();
			foreach ($parameterReflection as $parameter) {
				$attributes[] = $parameter->getName();
			}
		} catch (ReflectionException $e) {
			throw new RuntimeException("Component {$this->component} does not have a render method");
		}
		return array_flip($attributes);
	}
}
