<?php

namespace Bottledcode\SwytchFramework\Template;

use Bottledcode\SwytchFramework\Template\Interfaces\BeforeRenderInterface;
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
	 */
	public function __construct(
		public string $component,
		private FactoryInterface $container,
		private Compiler $compiler
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
	 * @return DOMDocument|DOMDocumentFragment
	 * @throws ContainerExceptionInterface
	 * @throws NotFoundExceptionInterface
	 * @throws DependencyException
	 * @throws NotFoundException
	 * @throws Exception
	 */
	public function compile(array $attributes = []): DOMDocument|DOMDocumentFragment
	{
		// we are about to render
		$component = $this->container->make($this->component);
		if ($component instanceof BeforeRenderInterface) {
			$component->aboutToRender($attributes);
		}

		$attributes = array_map(
			fn($attribute) => str_starts_with($attribute, '__REF__') ? $this->compiler->getRef($attribute) : $attribute,
			$attributes
		);

		// render the component
		$rendered = $component->render(...$attributes);

		$this->renderedComponent = $component;

		return $this->compiler->compile($rendered);
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
