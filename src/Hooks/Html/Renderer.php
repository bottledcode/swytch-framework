<?php

namespace Bottledcode\SwytchFramework\Hooks\Html;

use Bottledcode\SwytchFramework\Hooks\Handler;
use Bottledcode\SwytchFramework\Hooks\ProcessInterface;
use Bottledcode\SwytchFramework\Template\Parser\StreamingCompiler;
use DI\Container;
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

	public function __construct(
		private readonly StreamingCompiler $compiler,
		private readonly Psr17Factory $psr17Factory,
		private readonly Container $container
	) {
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
		$root = $this->container->make($this->root);
		$rendered = $this->container->call($root->render(...));
		$rendered = $this->compiler->compile($rendered);

		if(!empty($this->compiler->etagDescription)) {
			$response = $response->withHeader('ETag', $etag = "W\\".md5(implode("", $this->compiler->etagDescription)));
			if(($_SERVER['HTTP_IF_NONE_MATCH'] ?? null) && $etag === $_SERVER['HTTP_IF_NONE_MATCH']) {
				return $response->withStatus(304)->withHeader('Cache-Control', $this->compiler->tokenizer->render());
			}
		}

		return $response->withBody($this->psr17Factory->createStream($rendered))->withHeader('Cache-Control', $this->compiler->tokenizer->render());
	}
}
