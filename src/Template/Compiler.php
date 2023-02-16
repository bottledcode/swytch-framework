<?php

namespace Bottledcode\SwytchFramework\Template;

use Bottledcode\SwytchFramework\Template\Attributes\Component;
use Masterminds\HTML5;
use Masterminds\HTML5\Exception;
use olvlvl\ComposerAttributeCollector\Attributes;

final class Compiler
{
	private const OPTIONS = ['encode_entities' => false, 'disable_html_ns' => false];
	/**
	 * @var array<string, class-string>
	 */
	private array $components = [];

	public function __construct(public readonly string $template)
	{
	}

	public function compile(): string
	{
		$doc = $this->parse();
		return $this->renderCompiledHtml($doc);
	}

	/**
	 * @throws Exception
	 */
	private function parse(): \DOMDocument
	{
		$events = new TreeBuilder(false, self::OPTIONS, $this->components, $this);
		$scanner = new HTML5\Parser\Scanner(
			file_get_contents($this->template) ?: throw new \LogicException('Unable to open template'), 'UTF-8'
		);
		$parser = new HTML5\Parser\Tokenizer($scanner, $events, HTML5\Parser\Tokenizer::CONFORMANT_HTML);

		$parser->parse();

		return $events->document();
	}

	private function renderCompiledHtml(\DOMDocument|\DOMDocumentFragment $dom): string
	{
		$file = fopen('php://temp', 'wb');
		if ($file === false) {
			return '';
		}

		$rules = new Output($file, self::OPTIONS);
		$traverser = new HTML5\Serializer\Traverser($dom, $file, $rules, self::OPTIONS);

		$traverser->walk();

		$rules->unsetTraverser();

		$return = stream_get_contents($file, -1, 0);
		fclose($file);

		return $return ?: '';
	}

	/**
	 * @param class-string $component
	 * @return void
	 * @throws \ReflectionException
	 */
	public function registerComponent(string $component): void
	{
		try {
			/**
			 * @var array<\ReflectionAttribute<Component>> $attributes
			 */
			$attributes = Attributes::forClass($component)->classAttributes;
		} catch (\LogicException) {
			$attributes = [];
		}
		if (empty($attributes)) {
			$class = new \ReflectionClass($component);
			$attributes = $class->getAttributes(Component::class);
		}

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

	public function parseFragment(object|string $fragmentClass): array
	{
		$reflector = new \ReflectionClass($fragmentClass);
		$file = $reflector->getFileName();
		if ($file === false) {
			return [];
		}

		$content = file_get_contents($file);
		if ($content === false) {
			return [];
		}
		$fragments = [];
		while (true) {
			$content = strstr($content, '<<<HTML');
			if (empty($content)) {
				break;
			}
			$content = substr($content, strlen('<<<HTML'));
			$fragment = strstr($content, 'HTML;', true);
			if (empty($fragment)) {
				return [];
			}
			$fragment = substr($fragment, 0, strlen($fragment) - strlen('HTML;'));
			$fragments[] = $fragment;
		}

		$compiledFragments = [];

		foreach ($fragments as $fragment) {
			$events = new TreeBuilder(true, self::OPTIONS, $this->components, $this);
			$scanner = new HTML5\Parser\Scanner($fragment, 'UTF-8');
			$parser = new HTML5\Parser\Tokenizer($scanner, $events, HTML5\Parser\Tokenizer::CONFORMANT_HTML);

			$parser->parse();

			$compiledFragments[md5($fragment)] = [
				'compiled' => $this->renderCompiledHtml($events->fragment()),
				'original' => $fragment
			];
		}

		return $compiledFragments;
	}
}
