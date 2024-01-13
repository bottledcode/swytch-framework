<?php

namespace Bottledcode\SwytchFramework\Hooks\Api;

use Bottledcode\SwytchFramework\Cache\AbstractCache;
use Bottledcode\SwytchFramework\Cache\Control\GeneratesEtagInterface;
use Bottledcode\SwytchFramework\Cache\Control\Tokenizer;
use Bottledcode\SwytchFramework\Hooks\Handler;
use Bottledcode\SwytchFramework\Hooks\ProcessInterface;
use Bottledcode\SwytchFramework\Hooks\RequestType;
use Bottledcode\SwytchFramework\Router\Attributes\Route;
use Bottledcode\SwytchFramework\Router\Exceptions\InvalidRequest;
use Bottledcode\SwytchFramework\Template\Interfaces\StateProviderInterface;
use Bottledcode\SwytchFramework\Template\Parser\StreamingCompiler;
use DI\DependencyException;
use DI\FactoryInterface;
use DI\NotFoundException;
use Nyholm\Psr7\Factory\Psr17Factory;
use olvlvl\ComposerAttributeCollector\Attributes;
use olvlvl\ComposerAttributeCollector\TargetMethod;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use ReflectionClass;
use ReflectionException;
use ReflectionNamedType;
use Symfony\Component\Serializer\Serializer;

#[Handler(priority: 10)]
class Invoker extends ApiHandler implements ProcessInterface
{
	public function __construct(
		private readonly StateProviderInterface $stateProvider,
		private readonly Serializer $serializer,
		private readonly FactoryInterface $factory,
		private readonly Psr17Factory $psr17Factory
	) {
	}

	/**
	 * @throws NotFoundException
	 * @throws ReflectionException
	 * @throws DependencyException
	 */
	public function process(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
	{
		/**
		 * @var TargetMethod<Route>|null $route
		 */
		$route = $request->getAttribute(Router::ATTRIBUTE_HANDLER);
		$partArgs = (array)$request->getAttribute(Router::ATTRIBUTE_PATH_ARGS);

		$componentReflection = new ReflectionClass($route->class);
		$componentMethod = $componentReflection->getMethod($route->name);
		$componentParameters = $componentMethod->getParameters();
		$argPool = [...((array)$request->getParsedBody()), ...$partArgs];

		$arguments = [];
		foreach ($componentParameters as $parameter) {
			$parameterName = $parameter->getName();
			if ($parameterName === 'state') {
				$state = $request->getAttribute(
					Router::ATTRIBUTE_STATE
				) ?? throw new InvalidRequest(
					"State requested by {$route->class}::{$route->name} but not provided in request"
				);
				$arguments['state'] = $this->stateProvider->unserializeState($state);
				continue;
			}
			$type = $parameter->getType();
			if ($type instanceof ReflectionNamedType && $type->isBuiltin()) {
				$arguments[$parameterName] = $argPool[$parameterName] ?? ($type->allowsNull(
				) ? null : throw new InvalidRequest(
					"Missing required parameter {$parameterName} for {$route->class}::{$route->name}"
				));
				continue;
			}

			if ($type instanceof ReflectionNamedType) {
				switch (true) {
					case $type->getName() === ServerRequestInterface::class:
						$arguments[$parameterName] = $request;
						continue 2;
					case $type->getName() === ResponseInterface::class:
						$arguments[$parameterName] = $response;
						continue 2;
					case class_exists($type->getName()):
						$arguments[$parameterName] = $this->serializer->denormalize(
							$argPool,
							$type->getName()
						);
						continue 2;
				}
			}
			throw new InvalidRequest('Unsupported parameter type in ' . $route->class . '::' . $route->name);
		}
		$component = $this->factory->make($route->class);

		// now determine cache control headers
		$tokenizer = $this->factory->make(Tokenizer::class);
		$attributes = Attributes::forClass($component::class);
		foreach ($attributes->classAttributes as $attribute) {
			if ($attribute instanceof AbstractCache) {
				$tokenizer = $attribute->tokenize($tokenizer);
			}
		}
		foreach ($attributes->methodsAttributes[$componentMethod->name] as $attribute) {
			if ($attribute instanceof AbstractCache) {
				$tokenizer = $attribute->tokenize($tokenizer);
			}
		}

		if ($component instanceof GeneratesEtagInterface) {
			$response = $response->withHeader("ETag", $etag = "W\\" . md5(implode('', $component->getEtagComponents($arguments))));
			if(($_SERVER['HTTP_IF_NONE_MATCH'] ?? null) && $etag === $_SERVER['HTTP_IF_NONE_MATCH']) {
				return $response->withStatus(304)->withHeader('Cache-Control', $tokenizer->render());
			}
		}

		$result = $componentMethod->invokeArgs($component, $arguments);
		if ($this->currentType === RequestType::Htmx) {
			$compiler = $this->factory->make(StreamingCompiler::class, ['tokenizer' => $tokenizer]);
			$result = $compiler->compile($result);
			$tokenizer = $compiler->tokenizer;
		}
		if (is_string($result)) {
			return $response
				->withBody($this->psr17Factory->createStream($result))
				->withHeader('Cache-Control', $tokenizer->render());
		}

		if ($result instanceof ResponseInterface) {
			return $result;
		}
		return $response->withHeader('Cache-Control', $tokenizer->render());
	}
}
