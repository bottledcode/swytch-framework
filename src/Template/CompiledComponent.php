<?php

namespace Bottledcode\SwytchFramework\Template;

use Bottledcode\SwytchFramework\Template\Interfaces\BeforeRenderInterface;
use Bottledcode\SwytchFramework\Template\Interfaces\EscaperInterface;
use Psr\Container\ContainerInterface;
use ReflectionClass;

readonly class CompiledComponent
{
	public string $etag;

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
		$dom = $this->compile();
		$this->generateEtag($dom);
		return $this->compiler->renderCompiledHtml($dom);
	}

	private function generateEtag(\DOMDocument|\DOMDocumentFragment $dom): void {
		if(isset($this->etag)) {
			return;
		}
		if($dom instanceof \DOMDocument) {
			$this->etag = md5($dom->textContent);
		}
		if($dom instanceof \DOMDocumentFragment) {
			$this->etag = md5($dom->textContent);
		}
	}

	public function getUsedAttributes(): array {
		$attributes = [];
		try {
			$classReflection = new ReflectionClass($this->component);
			$methodReflection = $classReflection->getMethod('render');
			$parameterReflection = $methodReflection->getParameters();
			foreach($parameterReflection as $parameter) {
				$attributes[] = $parameter->getName();
			}
		} catch (\ReflectionException $e) {
			throw new \RuntimeException("Component {$this->component} does not have a render method");
		}
		return array_flip($attributes);
	}

	public function compile(array $attributes = []): \DOMDocument|\DOMDocumentFragment
	{
		// we are about to render
		$component = $this->container->get($this->component);
		if ($component instanceof BeforeRenderInterface) {
			$component->aboutToRender($attributes);
		}

		$attributes = array_map(
			fn($attribute) => str_starts_with($attribute, '__REF__') ? $this->compiler->getRef($attribute) : $attribute,
			$attributes
		);

		// render the component
		$rendered = $component->render(...$attributes);

		return $this->compiler->compile($rendered);
	}
}
