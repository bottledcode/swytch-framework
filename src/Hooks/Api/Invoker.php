<?php

namespace Bottledcode\SwytchFramework\Hooks\Api;

use Bottledcode\SwytchFramework\Hooks\ProcessInterface;
use Bottledcode\SwytchFramework\Router\Attributes\Route;
use Bottledcode\SwytchFramework\Router\Exceptions\InvalidRequest;
use Bottledcode\SwytchFramework\Template\Interfaces\StateProviderInterface;
use DI\FactoryInterface;
use Nyholm\Psr7\Factory\Psr17Factory;
use olvlvl\ComposerAttributeCollector\TargetMethod;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Symfony\Component\Serializer\Serializer;

class Invoker extends ApiHandler implements ProcessInterface
{
	public function __construct(
		private readonly StateProviderInterface $stateProvider,
		private readonly Serializer $serializer,
		private readonly FactoryInterface $factory,
		private readonly Psr17Factory $psr17Factory
	) {
	}

	public function process(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
	{
		/**
		 * @var TargetMethod<Route>|null $route
		 */
		$route = $request->getAttribute(Router::ATTRIBUTE_HANDLER);
		$partArgs = $request->getAttribute(Router::ATTRIBUTE_PATH_ARGS);

		$componentReflection = new \ReflectionClass($route->class);
		$componentMethod = $componentReflection->getMethod($route->name);
		$componentParameters = $componentMethod->getParameters();

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
			if ($type instanceof \ReflectionNamedType && $type->isBuiltin()) {
				$arguments[$parameterName] = $request->getParsedBody()[$parameterName] ?? ($type->allowsNull(
				) ? null : throw new InvalidRequest(
					"Missing required parameter {$parameterName} for {$route->class}::{$route->name}"
				));
				continue;
			}

			if ($type instanceof \ReflectionNamedType) {
				switch (true) {
					case $type->getName() === ServerRequestInterface::class:
						$arguments[$parameterName] = $request;
						continue 2;
					case $type->getName() === ResponseInterface::class:
						$arguments[$parameterName] = $response;
						continue 2;
					case class_exists($type->getName()):
						$arguments[$parameterName] = $this->serializer->denormalize(
							$request->getParsedBody(),
							$type->getName()
						);
						continue 2;
				}
			}
			throw new InvalidRequest('Unsupported parameter type in ' . $route->class . '::' . $route->name);
		}
		$component = $this->factory->make($route->class);
		$result = $componentMethod->invokeArgs($component, $arguments);
		if (is_string($result)) {
			return $response->withBody($this->psr17Factory->createStream($result));
		} elseif ($result instanceof ResponseInterface) {
			return $result;
		}
		return $response;
	}
}
