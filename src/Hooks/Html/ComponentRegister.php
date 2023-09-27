<?php

namespace Bottledcode\SwytchFramework\Hooks\Html;

use Bottledcode\SwytchFramework\Hooks\Handler;
use Bottledcode\SwytchFramework\Hooks\HandleRequestInterface;
use Bottledcode\SwytchFramework\Hooks\PreprocessInterface;
use Bottledcode\SwytchFramework\Hooks\RequestType;
use Bottledcode\SwytchFramework\Template\Attributes\Component;
use Bottledcode\SwytchFramework\Template\Parser\StreamingCompiler;
use olvlvl\ComposerAttributeCollector\Attributes;
use olvlvl\ComposerAttributeCollector\TargetClass;
use Psr\Http\Message\ServerRequestInterface;

#[Handler(1)]
readonly class ComponentRegister implements PreprocessInterface, HandleRequestInterface
{
	public function __construct(private StreamingCompiler $compiler)
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

	public function handles(RequestType $requestType): bool
	{
		return $requestType === RequestType::Browser or $requestType === RequestType::Htmx;
	}
}
