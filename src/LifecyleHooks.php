<?php

namespace Bottledcode\SwytchFramework;

use Bottledcode\SwytchFramework\Hooks\ExceptionHandlerInterface;
use Bottledcode\SwytchFramework\Hooks\Handler;
use Bottledcode\SwytchFramework\Hooks\HandleRequestInterface;
use Bottledcode\SwytchFramework\Hooks\PostprocessInterface;
use Bottledcode\SwytchFramework\Hooks\PreprocessInterface;
use Bottledcode\SwytchFramework\Hooks\ProcessInterface;
use Bottledcode\SwytchFramework\Hooks\RequestDeterminatorInterface;
use Bottledcode\SwytchFramework\Hooks\RequestType;
use olvlvl\ComposerAttributeCollector\Attributes;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\ContainerInterface;
use Psr\Container\NotFoundExceptionInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use ReflectionClass;
use ReflectionException;
use Throwable;

/**
 * This class is responsible for managing the lifecycle of a request and is the engine of the framework. You can add
 * your own middleware, preprocessors, processors, and postprocessors to the lifecycle by using the appropriate methods.
 */
class LifecyleHooks
{
	/**
	 * Create a lifecycle hooks object. This will automatically find all classes that implement HandleRequestInterface.
	 *
	 * @param ContainerInterface $container
	 * @param array<int, array<PreprocessInterface&HandleRequestInterface>> $preprocessors
	 * @param array<int, array<ProcessInterface&HandleRequestInterface>> $processors
	 * @param array<int, array<PostprocessInterface&HandleRequestInterface>> $postprocessors
	 * @param array<int, array<RequestDeterminatorInterface>> $middleware
	 * @param array<int, array<ExceptionHandlerInterface>> $exceptionHandlers
	 * @throws ContainerExceptionInterface
	 * @throws NotFoundExceptionInterface
	 */
	public function __construct(
		private readonly ContainerInterface $container,
		private array $preprocessors = [],
		private array $processors = [],
		private array $postprocessors = [],
		private array $middleware = [],
		private array $exceptionHandlers = [],
	) {
	}

	public function load(): static
	{
		$classes = Attributes::findTargetClasses(Handler::class);
		foreach ($classes as $class) {
			try {
				$reflection = new ReflectionClass($class->name);
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
				if ($reflection->implementsInterface(ExceptionHandlerInterface::class)) {
					$this->handleExceptionWith($this->container->get($class->name), $class->attribute->priority);
				}
			} catch (ReflectionException) {
				continue;
			}
		}

		return $this;
	}

	/**
	 * Before any response is calculated, a decision must be made about the request (ex. routers). Proprocessors allow
	 * that to happen.
	 *
	 * @param HandleRequestInterface&PreprocessInterface $preprocessor The preprocessor to add
	 * @param int $priority The priority of the preprocessor. Lower priorities are call first.
	 * @return $this
	 */
	public function preprocessWith(PreprocessInterface&HandleRequestInterface $preprocessor, int $priority): static
	{
		$this->preprocessors[$priority][] = $preprocessor;
		return $this;
	}

	/**
	 * Process the request and return a response. These are renderers, controllers, etc.
	 *
	 * @param HandleRequestInterface&ProcessInterface $processor The processor
	 * @param int $priority The priority
	 * @return $this
	 */
	public function processWith(ProcessInterface&HandleRequestInterface $processor, int $priority): static
	{
		$this->processors[$priority][] = $processor;
		return $this;
	}

	/**
	 * Postprocess the response. These are things like caching, compression, etc.
	 *
	 * @param HandleRequestInterface&PostprocessInterface $postprocessor The postprocessor
	 * @param int $priority The priority
	 * @return $this
	 */
	public function postprocessWith(PostprocessInterface&HandleRequestInterface $postprocessor, int $priority): static
	{
		$this->postprocessors[$priority][] = $postprocessor;
		return $this;
	}

	/**
	 * Add a request determinator. This is used to determine if other processors should response to a request.
	 *
	 * @param RequestDeterminatorInterface $middleware The middleware
	 * @param int $priority The priority
	 * @return $this
	 */
	public function determineTypeWith(RequestDeterminatorInterface $middleware, int $priority): static
	{
		$this->middleware[$priority][] = $middleware;
		return $this;
	}

	/**
	 * Add an exception handler, use this for custom error pages, etc.
	 * @param ExceptionHandlerInterface $handler
	 * @param int $priority
	 * @return $this
	 */
	public function handleExceptionWith(ExceptionHandlerInterface $handler, int $priority): static
	{
		$this->exceptionHandlers[$priority][] = $handler;
		return $this;
	}

	/**
	 * Remove a preprocessor.
	 *
	 * @param HandleRequestInterface&PreprocessInterface $preprocessor The preprocessor to remove
	 * @param int $priority The priority of the preprocessor.
	 * @return $this
	 */
	public function removePreprocessor(PreprocessInterface&HandleRequestInterface $preprocessor, int $priority): static
	{
		$this->preprocessors[$priority] = array_filter(
			$this->preprocessors[$priority],
			static fn(PreprocessInterface&HandleRequestInterface $x) => $x !== $preprocessor
		);
		return $this;
	}

	/**
	 * Reset preprocessors. (useful for testing)
	 *
	 * @return $this
	 */
	public function resetPreprocessors(): static
	{
		$this->preprocessors = [];
		return $this;
	}

	/**
	 * Remove a processor.
	 *
	 * @param HandleRequestInterface&ProcessInterface $processor
	 * @param int $priority
	 * @return $this
	 */
	public function removeProcessor(ProcessInterface&HandleRequestInterface $processor, int $priority): static
	{
		$this->processors[$priority] = array_filter(
			$this->processors[$priority],
			static fn(ProcessInterface&HandleRequestInterface $x) => $x !== $processor
		);
		return $this;
	}

	/**
	 * Reset processors. (useful for testing)
	 * @return $this
	 */
	public function resetProcessors(): static
	{
		$this->processors = [];
		return $this;
	}

	/**
	 * Remove a postprocessor.
	 *
	 * @param HandleRequestInterface&PostprocessInterface $postprocessor
	 * @param int $priority
	 * @return $this
	 */
	public function removePostprocessor(
		PostprocessInterface&HandleRequestInterface $postprocessor,
		int $priority
	): static {
		$this->postprocessors[$priority] = array_filter(
			$this->postprocessors[$priority],
			static fn(PostprocessInterface&HandleRequestInterface $x) => $x !== $postprocessor
		);
		return $this;
	}

	/**
	 * Reset postprocessors. (useful for testing)
	 * @return $this
	 */
	public function resetPostprocessors(): static
	{
		$this->postprocessors = [];
		return $this;
	}

	/**
	 * Remove a request determinator.
	 * @param RequestDeterminatorInterface $middleware
	 * @param int $priority
	 * @return $this
	 */
	public function removeDeterminator(RequestDeterminatorInterface $middleware, int $priority): static
	{
		$this->middleware[$priority] = array_filter(
			$this->middleware[$priority],
			static fn(RequestDeterminatorInterface $x) => $x !== $middleware
		);
		return $this;
	}

	/**
	 * Reset request determinators. (useful for testing)
	 * @return $this
	 */
	public function resetDeterminators(): static
	{
		$this->middleware = [];
		return $this;
	}

	/**
	 * Remove an exception handler.
	 * @param ExceptionHandlerInterface $handler
	 * @param int $priority
	 * @return $this
	 */
	public function removeExceptionHandler(ExceptionHandlerInterface $handler, int $priority): static
	{
		$this->exceptionHandlers[$priority] = array_filter(
			$this->exceptionHandlers[$priority],
			static fn(ExceptionHandlerInterface $x) => $x !== $handler
		);
		return $this;
	}

	/**
	 * Reset exception handlers. (useful for testing)
	 * @return $this
	 */
	public function resetExceptionHandlers(): static
	{
		$this->exceptionHandlers = [];
		return $this;
	}

	/**
	 * Run the request through the preprocessor stack, transforming the request.
	 *
	 * @param ServerRequestInterface $request
	 * @param RequestType $requestType
	 * @return ServerRequestInterface
	 */
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

	/**
	 * Run the request through the processor stack, transforming the response.
	 *
	 * @param ServerRequestInterface $request
	 * @param RequestType $requestType
	 * @param ResponseInterface $response
	 * @return ResponseInterface
	 */
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

	/**
	 * Run the response through the postprocessor stack, transforming the response.
	 * @param ResponseInterface $response
	 * @param RequestType $type
	 * @return ResponseInterface
	 */
	public function postProcess(ResponseInterface $response, RequestType $type): ResponseInterface
	{
		ksort($this->postprocessors);
		return array_reduce(
			array_filter(
				array_merge(...$this->postprocessors),
				static fn(HandleRequestInterface $x) => $x->handles($type)
			),
			static fn(ResponseInterface $carry, PostprocessInterface $processor) => $processor->postprocess($carry),
			$response
		);
	}

	/**
	 * Determine the request type.
	 * @param ServerRequestInterface $request
	 * @return RequestType
	 */
	public function determineType(ServerRequestInterface $request): RequestType
	{
		ksort($this->middleware);
		return array_reduce(
			array_merge(...$this->middleware),
			static fn(RequestType $carry, RequestDeterminatorInterface $middleware) => $middleware->currentRequestIs(
				$request,
				$carry
			),
			RequestType::Unknown
		);
	}

	/**
	 * Handle an exception.
	 * @param Throwable $exception
	 * @param RequestType $type
	 * @param ServerRequestInterface $request
	 * @param ResponseInterface $response
	 * @return ResponseInterface
	 */
	public function handleException(
		Throwable $exception,
		RequestType $type,
		ServerRequestInterface $request,
		ResponseInterface $response
	): ResponseInterface {
		ksort($this->exceptionHandlers);
		$canHandle = array_filter(
			array_merge(...$this->exceptionHandlers),
			fn(ExceptionHandlerInterface $x) => $x->canHandle($exception, $type)
		);
		return array_reduce(
			$canHandle,
			static fn(ResponseInterface $response, ExceptionHandlerInterface $handler) => $handler->handleException(
				$exception,
				$request,
				$response
			),
			$response
		);
	}
}
