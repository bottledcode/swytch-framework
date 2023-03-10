<?php

namespace Bottledcode\SwytchFramework\Hooks\Html;

use Bottledcode\SwytchFramework\Hooks\Handler;
use Bottledcode\SwytchFramework\Hooks\ProcessInterface;
use Bottledcode\SwytchFramework\Template\Compiler;
use Nyholm\Psr7\Factory\Psr17Factory;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

#[Handler(10)]
class Renderer extends HtmlHandler implements ProcessInterface
{
	/**
	 * @var class-string
	 */
	private string $root;

	public function __construct(private readonly Compiler $compiler, private readonly Psr17Factory $psr17Factory)
	{
	}

	/**
	 * Set the root component of the application.
	 *
	 * @param class-string $root
	 * @return $this
	 */
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
