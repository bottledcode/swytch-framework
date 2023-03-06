<?php

namespace Bottledcode\SwytchFramework\Hooks\Html;

use Bottledcode\SwytchFramework\Hooks\ProcessInterface;
use Bottledcode\SwytchFramework\Template\Compiler;
use Nyholm\Psr7\Factory\Psr17Factory;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

class Renderer extends HtmlHandler implements ProcessInterface
{
	private string $root;

	public function __construct(private readonly Compiler $compiler, private readonly Psr17Factory $psr17Factory)
	{
	}

	public function setRoot(string $root): static
	{
		$this->root = $root;
		return $this;
	}

	public function process(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
	{
		$component = $this->compiler->compileComponent($this->root);
		$rendered = $component->renderToString();
		return $response->withBody($this->psr17Factory->createStream($rendered));
	}
}
