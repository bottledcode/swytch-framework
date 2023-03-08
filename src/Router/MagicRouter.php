<?php

namespace Bottledcode\SwytchFramework\Router;

use Bottledcode\SwytchFramework\LifecyleHooks;
use Bottledcode\SwytchFramework\Router\Attributes\From;
use Nyholm\Psr7\Factory\Psr17Factory;
use Nyholm\Psr7Server\ServerRequestCreatorInterface;
use Psr\Container\ContainerInterface;
use Psr\Http\Message\ResponseInterface;

readonly class MagicRouter
{
	public function __construct(private ContainerInterface $container)
	{
	}

	public function go(): ResponseInterface
	{
		/**
		 * @var LifecyleHooks $hooks
		 */
		$hooks = $this->container->get(LifecyleHooks::class);
		/**
		 * @var ServerRequestCreatorInterface $requestFactory
		 */
		$requestFactory = $this->container->get(ServerRequestCreatorInterface::class);
		$response = $this->container->get(ResponseInterface::class);

		$request = $requestFactory->fromGlobals();

		$requestType = $hooks->determineType($request);

		try {
			$request = $hooks->preprocess($request, $requestType);
			$response = $hooks->process($request, $requestType, $response);
			$response = $hooks->postProcess($response, $requestType);
		} catch (\Throwable $e) {
			/**
			 * @var Psr17Factory $factory
			 */
			$factory = $this->container->get(Psr17Factory::class);
			$response = $factory->createResponse(200);
			return $hooks->handleException($e, $requestType, $request, $response);
		}
		return $response;
	}
}
