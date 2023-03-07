<?php

namespace Bottledcode\SwytchFramework;

use Bottledcode\SwytchFramework\Hooks\Handler;
use Bottledcode\SwytchFramework\Hooks\HandleRequestInterface;
use Bottledcode\SwytchFramework\Hooks\PostprocessInterface;
use Bottledcode\SwytchFramework\Hooks\PreprocessInterface;
use Bottledcode\SwytchFramework\Hooks\ProcessInterface;
use Bottledcode\SwytchFramework\Hooks\RequestDeterminatorInterface;
use Bottledcode\SwytchFramework\Hooks\RequestType;
use olvlvl\ComposerAttributeCollector\Attributes;
use Psr\Container\ContainerInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

class LifecyleHooks
{
	public function __construct(
		private readonly ContainerInterface $container,
		private array $preprocessors = [],
		private array $processors = [],
		private array $postprocessors = [],
		private array $middleware = []
	) {
		$classes = Attributes::findTargetClasses(Handler::class);
		foreach ($classes as $class) {
			try {
				$reflection = new \ReflectionClass($class->name);
				if ($reflection->implementsInterface(HandleRequestInterface::class)) {
					if ($reflection->implementsInterface(PreprocessInterface::class)) {
						$this->preprocessWith($this->container->get($class->name), $class->attribute->priority);
					}
					if ($reflection->implementsInterface(ProcessInterface::class)) {
						$this->processWith($this->container->get($class->name), $class->attribute->priority);
					}
					if ($reflection->implementsInterface(PostprocessInterface::class)) {
						$this->postprocessWith($this->container->get($class->name), $class->attribute->priority);
					}
				}
				if ($reflection->implementsInterface(RequestDeterminatorInterface::class)) {
					$this->determineTypeWith($this->container->get($class->name), $class->attribute->priority);
				}
			} catch (\ReflectionException) {
				continue;
			}
		}
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
		$this->postprocessors[$priority][] = $postprocessor;
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
		$processors = array_merge(...$this->preprocessors);
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
