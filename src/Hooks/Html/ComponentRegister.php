<?php

namespace Bottledcode\SwytchFramework\Hooks\Html;

use Bottledcode\SwytchFramework\Hooks\Handler;
use Bottledcode\SwytchFramework\Hooks\PreprocessInterface;
use Bottledcode\SwytchFramework\Hooks\RequestType;
use Bottledcode\SwytchFramework\Template\Attributes\Component;
use Bottledcode\SwytchFramework\Template\Compiler;
use olvlvl\ComposerAttributeCollector\Attributes;
use olvlvl\ComposerAttributeCollector\TargetClass;
use Psr\Http\Message\ServerRequestInterface;

#[Handler(1)]
class ComponentRegister extends HtmlHandler implements PreprocessInterface
{
	public function __construct(private readonly Compiler $compiler)
	{
	}

	public function preprocess(ServerRequestInterface $request, RequestType $type): ServerRequestInterface
	{
		array_map(
			fn(TargetClass $class) => $this->compiler->registerComponent($class),
			Attributes::findTargetClasses(Component::class)
		);
		return $request;
	}
}
