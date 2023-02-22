<?php

namespace Bottledcode\SwytchFramework\Template;

use Bottledcode\SwytchFramework\Template\Attributes\Component;
use Bottledcode\SwytchFramework\Template\Escapers\Variables;
use Bottledcode\SwytchFramework\Template\Interfaces\EscaperInterface;
use Bottledcode\SwytchFramework\Template\Interfaces\RefProviderInterface;
use Bottledcode\SwytchFramework\Template\ReferenceImplementation\SimpleRefProvider;
use Laminas\Escaper\Escaper;
use Masterminds\HTML5;
use Masterminds\HTML5\Exception;
use olvlvl\ComposerAttributeCollector\TargetClass;
use Psr\Container\ContainerInterface;

final class Compiler
{
	private const OPTIONS = ['encode_entities' => false, 'disable_html_ns' => false];
	/**
	 * @var array<string, class-string>
	 */
	private array $components = [];

	private \DOMDocument|null $doc = null;

	private readonly RefProviderInterface $refProvider;

	private readonly EscaperInterface $escaper;

	public function __construct(private readonly ContainerInterface $container)
	{
		if ($this->container->has(RefProviderInterface::class)) {
			$this->refProvider = $this->container->get(RefProviderInterface::class);
		} else {
			$this->refProvider = new SimpleRefProvider();
			if(method_exists($this->container, 'set')) {
				$this->container->set(RefProviderInterface::class, $this->refProvider);
			}
		}

		if($this->container->has(EscaperInterface::class)) {
			$this->escaper = $this->container->get(EscaperInterface::class);
		} else {
			$this->escaper = new Variables();
			if(method_exists($this->container, 'set')) {
				$this->container->set(EscaperInterface::class, $this->escaper);
			}
		}
	}

	public function createRef(mixed $item): string
	{
		return $this->refProvider->createRef($item);
	}

	public function deleteRef(string $id): void
	{
		$this->refProvider->deleteRef($id);
	}

	public function getRef(string $id): mixed
	{
		return $this->refProvider->getRef($id);
	}

	/**
	 * @param class-string|TargetClass<Component> $component
	 * @return void
	 * @throws \ReflectionException
	 */
	public function registerComponent(string|TargetClass $component): void
	{
		if ($component instanceof TargetClass) {
			$this->components[mb_strtolower($component->attribute->name)] = $component->name;
			return;
		}

		$class = new \ReflectionClass($component);
		$attributes = $class->getAttributes(Component::class);


		foreach ($attributes as $attribute) {
			if ($attribute->getName() === Component::class) {
				$name = mb_strtolower($attribute->newInstance()->name);
				$this->components[$name] = $component;
			}
		}

		if (empty($attributes)) {
			throw new \LogicException("Component $component is not annotated with the Component attribute");
		}
	}

	public function compileComponent(string $componentName): CompiledComponent
	{
		return new CompiledComponent($componentName, $this->container, $this);
	}

	public function compile(string $html): \DOMDocument|\DOMDocumentFragment
	{
		$isFragment = !str_contains($html, '<html>');
		if (str_contains($html, '</body>')) {
			$html = str_replace('</body>', '<script src="https://unpkg.com/htmx.org@1.8.5"></script></body>', $html);
		}

		$html = $this->escaper->makeBlobs($html);

		$events = new TreeBuilder(
			$isFragment,
			[...self::OPTIONS, 'target_document' => $this->doc],
			$this->components,
			$this,
			$this->container
		);
		if ($this->doc === null) {
			$this->doc = $events->document();
		}
		$scanner = new HTML5\Parser\Scanner($html, 'UTF-8');
		$parser = new HTML5\Parser\Tokenizer($scanner, $events, HTML5\Parser\Tokenizer::CONFORMANT_HTML);

		$parser->parse();

		return $isFragment ? $events->fragment() : $events->document();
	}

	/**
	 * @throws Exception
	 */

	public function renderCompiledHtml(\DOMDocument|\DOMDocumentFragment $document): string
	{
		$file = fopen('php://temp', 'wb');
		if ($file === false) {
			return '';
		}

		$rules = new Output($file, self::OPTIONS);
		$rules->setEscaper($this->container->get(Escaper::class));
		$rules->setBlobber($this->escaper);
		$traverser = new HTML5\Serializer\Traverser($document, $file, $rules, self::OPTIONS);

		$traverser->walk();

		$rules->unsetTraverser();

		$return = stream_get_contents($file, -1, 0);
		fclose($file);

		return $return ?: '';
	}
}
