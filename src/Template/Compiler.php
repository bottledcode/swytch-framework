<?php

namespace Bottledcode\SwytchFramework\Template;

use Bottledcode\SwytchFramework\Template\Attributes\Component;
use Masterminds\HTML5;
use Masterminds\HTML5\Exception;
use olvlvl\ComposerAttributeCollector\Attributes;

final class Compiler
{
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
        $html = new HTML5();
        return $html->saveHTML($doc);
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
                $name = $attribute->newInstance()->name;
                $this->components[$name] = $component;
            }
        }

        if (empty($attributes)) {
            throw new \LogicException("Component $component is not annotated with the Component attribute");
        }
    }

    /**
     * @throws Exception
     */
    private function parse(): \DOMDocument
    {
        $options = ['encode_entities' => false, 'disable_html_ns' => false];
        $events = new TreeBuilder(false, $options, $this->components, $this);
        $scanner = new HTML5\Parser\Scanner(
            file_get_contents($this->template) ?: throw new \LogicException('Unable to open template'), 'UTF-8'
        );
        $parser = new HTML5\Parser\Tokenizer($scanner, $events, HTML5\Parser\Tokenizer::CONFORMANT_HTML);

        $parser->parse();

        return $events->document();
    }

    public function parseFragment(object $fragment): \DOMDocumentFragment
    {

        $options = ['encode_entities' => false, 'disable_html_ns' => false];
        $events = new TreeBuilder(true, $options, $this->components, $this);
        $scanner = new HTML5\Parser\Scanner($fragment, 'UTF-8');
        $parser = new HTML5\Parser\Tokenizer($scanner, $events, HTML5\Parser\Tokenizer::CONFORMANT_HTML);

        $parser->parse();

        return $events->fragment();
    }
}
