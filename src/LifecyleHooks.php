<?php

namespace Bottledcode\SwytchFramework;

use Bottledcode\SwytchFramework\Hooks\HandleRequestInterface;
use Bottledcode\SwytchFramework\Hooks\PostprocessInterface;
use Bottledcode\SwytchFramework\Hooks\PreprocessInterface;
use Bottledcode\SwytchFramework\Hooks\ProcessInterface;
use Bottledcode\SwytchFramework\Hooks\RequestDeterminatorInterface;
use Bottledcode\SwytchFramework\Hooks\RequestType;
use Bottledcode\SwytchFramework\Template\Interfaces\EscaperInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

class LifecyleHooks
{
	public function __construct(
		private EscaperInterface $escaper,
		private array $preprocessors = [],
		private array $processors = [],
		private array $postprocessors = [],
		private array $middleware = []
	) {
	}


	/**
	 * @param HandleRequestInterface&PreprocessInterface $preprocessor
	 * @param int $priority
	 * @return $this
	 */
	public function preprocessWith(PreprocessInterface&HandleRequestInterface $preprocessor, int $priority): static
	{
		$this->preprocessors[$priority][] = $preprocessor;
		return $this;
	}

	public function processWith(ProcessInterface&HandleRequestInterface $processor, int $priority): static
	{
		$this->processors[$priority][] = $processor;
		return $this;
	}

	public function postprocessWith(PostprocessInterface&HandleRequestInterface $postprocessor, int $priority): static
	{
		$this->postprocessors[$priority][] = $priority;
		return $this;
	}

	public function escapeWith(EscaperInterface $escaper): static
	{
		$this->escaper = $escaper;
		return $this;
	}

	public function determineTypeWith(RequestDeterminatorInterface $middleware, int $priority): static
	{
		$this->middleware[$priority][] = $middleware;
		return $this;
	}

	public function preprocess(ServerRequestInterface $request, RequestType $requestType): ServerRequestInterface
	{
		ksort($this->preprocessors);
		$processors = array_merge(...$this->processors);
		$processors = array_filter($processors, static fn(HandleRequestInterface $x) => $x->handles($requestType));
		return array_reduce(
			$processors,
			static fn(ServerRequestInterface $request, PreprocessInterface $processor) => $processor->preprocess(
				$request,
				$requestType
			),
			$request
		);
	}

	public function process(
		ServerRequestInterface $request,
		RequestType $requestType,
		ResponseInterface $response
	): ResponseInterface {
		ksort($this->processors);
		$processors = array_merge(...$this->processors);
		$processors = array_filter($processors, static fn(HandleRequestInterface $x) => $x->handles($requestType));
		return array_reduce(
			$processors,
			static fn(ResponseInterface $response, ProcessInterface $processor) => $processor->process(
				$request,
				$response
			),
			$response
		);
	}

	public function postProcess(ResponseInterface $response, RequestType $type): ResponseInterface
	{
		ksort($this->postprocessors);
		return array_reduce(
			array_filter(
				array_merge(...$this->postprocessors),
				fn(HandleRequestInterface $x) => $x->handles($type)
			),
			fn(ResponseInterface $carry, PostprocessInterface $processor) => $processor->postprocess($carry),
			$response
		);
	}

	public function determineType(ServerRequestInterface $request): RequestType
	{
		ksort($this->middleware);
		return array_reduce(
			array_merge(...$this->middleware),
			fn(RequestType $carry, RequestDeterminatorInterface $middleware) => $middleware->currentRequestIs(
				$request,
				$carry
			),
			RequestType::Unknown
		);
	}
}
