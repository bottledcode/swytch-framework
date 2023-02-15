<?php

namespace Bottledcode\SwytchFramework\Template;

use Masterminds\HTML5\Parser\DOMTreeBuilder;

class TreeBuilder extends DOMTreeBuilder
{
    public function __construct(bool $isFragment, array $options, private array $components, private Compiler $compiler)
    {
        parent::__construct($isFragment, $options);
    }

    public function startTag($name, $attributes = array(), $selfClosing = false)
    {
        $mode = parent::startTag($name, $attributes, $selfClosing);
        if(array_key_exists($name, $this->components)) {
            $component = new $this->components[$name]();
            $content = $component->render();
            $node= $this->doc->createTextNode($content);
            $this->current->appendChild($node);
        }
        return $mode;
    }
}
