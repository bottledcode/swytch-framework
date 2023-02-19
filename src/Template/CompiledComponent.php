<?php

namespace Bottledcode\SwytchFramework\Template;

use Bottledcode\SwytchFramework\Template\Interfaces\BeforeRenderInterface;
use Psr\Container\ContainerInterface;
use ReflectionClass;

readonly class CompiledComponent
{
	/**
	 * @param class-string $component
	 * @param ContainerInterface $container
	 * @param Compiler $compiler
	 */
	public function __construct(
		public string $component,
		private ContainerInterface $container,
		private Compiler $compiler
	) {
	}

	public function renderToString(): string
	{
		return $this->compiler->renderCompiledHtml($this->compile());
	}

	public function compile(array &$attributes = []): \DOMDocument|\DOMDocumentFragment
	{
		// we are about to render
		$component = $this->container->get($this->component);
		if ($component instanceof BeforeRenderInterface) {
			$component->aboutToRender($attributes);
		}

		$myAttributes = array_map(static fn($attribute) => is_string($attribute) ? trim($attribute, '{}') : $attribute,
			$attributes);

		$myAttributes = array_map(
			fn($attribute) => str_starts_with($attribute, '__REF__') ? $this->compiler->getRef($attribute) : $attribute,
			$myAttributes
		);

		$attributes = [];

		try {
			$classReflection = new ReflectionClass($this->component);
			$methodReflection = $classReflection->getMethod('render');
			$parameterReflection = $methodReflection->getParameters();
			foreach($parameterReflection as $parameter) {
				if(array_key_exists($parameter->getName(), $myAttributes)) {
					$attributes[$parameter->getName()] = $myAttributes[$parameter->getName()];
				}
			}
		} catch (\ReflectionException $e) {
			throw new \RuntimeException("Component {$this->component} does not have a render method");
		}

		// render the component
		$rendered = $component->render(...$attributes);

		// sanitize html tags, proper escaping comes later
		preg_match_all('@\{([^/\{\}\x00-\x1F=]++)@', $rendered, $matches);
		foreach ($matches[1] as $match) {
			$new = str_replace(['<', '>'], ['&lt;', '&gt;'], $match);
			$rendered = str_replace("{{$match}}", "{{$new}}", $rendered);
		}

		return $this->compiler->compile($rendered);
	}
}
