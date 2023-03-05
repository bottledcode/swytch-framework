<?php

namespace Bottledcode\SwytchFramework;

use Bottledcode\SwytchFramework\Hooks\HandleRequestInterface;
use Bottledcode\SwytchFramework\Hooks\PostprocessInterface;
use Bottledcode\SwytchFramework\Hooks\PreprocessInterface;
use Bottledcode\SwytchFramework\Hooks\RequestDeterminatorInterface;
use Bottledcode\SwytchFramework\Hooks\RequestType;
use Bottledcode\SwytchFramework\Template\Interfaces\EscaperInterface;

class LifecyleHooks
{
	public function __construct(
		private EscaperInterface $escaper,
		private array $preprocessors = [],
		private array $postprocessors = [],
		private array $middleware = []
	) {
	}

	public function preprocessWith(PreprocessInterface&HandleRequestInterface $preprocessor, int $priority): static
	{
		$this->postprocessors[$priority][] = $preprocessor;
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

	public function withMiddleware(RequestDeterminatorInterface $middleware, int $priority): static
	{
		$this->middleware[$priority][] = $this;
		return $this;
	}

	public function preprocess(string $request, RequestType $type): string
	{
		ksort($this->preprocessors);
		$flatten = array_merge(...$this->preprocessors);
		$flatten = array_filter($flatten, fn(HandleRequestInterface $x) => $x->handles($type));
		return array_reduce(
			$flatten,
			fn(string $carry, PreprocessInterface $processor) => $processor->process($carry),
			$request
		);
	}

	public function postProcess(string $request, RequestType $type): string
	{
		ksort($this->postprocessors);
		return array_reduce(
			array_filter(
				array_merge(...$this->postprocessors),
				fn(HandleRequestInterface $x) => $x->handles($type)
			),
			fn(string $carry, PostprocessInterface $processor) => $processor->process($carry),
			$request
		);
	}

	public function middleProcess(string $request): RequestType
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
