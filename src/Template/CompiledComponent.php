<?php

namespace Bottledcode\SwytchFramework\Template;

use Bottledcode\SwytchFramework\Template\Interfaces\BeforeRenderInterface;
use Psr\Container\ContainerInterface;

readonly class CompiledComponent
{
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

	public function compile(array $attributes = []): \DOMDocument|\DOMDocumentFragment
	{
		// we are about to render
		$component = $this->container->get($this->component);
		if ($component instanceof BeforeRenderInterface) {
			$component->aboutToRender($attributes);
		}

		// todo: render components in attributes?
		$attributes = array_map(static fn($attribute) => is_string($attribute) ? trim($attribute, '{}') : $attribute, $attributes);

		// render the component
		$rendered = $component->render(...$attributes);

		// sanitize html tags, proper escaping comes later
		preg_match_all('@\{([^/\{\}\x00-\x1F=]++)@', $rendered, $matches);
		foreach($matches[1] as $match) {
			$new = str_replace(['<', '>'], ['&lt;', '&gt;'], $match);
			$rendered = str_replace("{{$match}}", "{{$new}}", $rendered);
		}

		return $this->compiler->compile($rendered);
	}
}
