<?php

namespace Bottledcode\SwytchFramework\Router;

use Bottledcode\SwytchFramework\LifecyleHooks;
use Nyholm\Psr7\Factory\Psr17Factory;
use Nyholm\Psr7Server\ServerRequestCreatorInterface;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\ContainerInterface;
use Psr\Container\NotFoundExceptionInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Throwable;

readonly class MagicRouter
{
	public function __construct(private ContainerInterface $container)
	{
	}

	/**
	 * @throws NotFoundExceptionInterface
	 * @throws ContainerExceptionInterface
	 */
	public function go(): ResponseInterface
	{
		/**
		 * @var LifecyleHooks $hooks
		 */
		$hooks = $this->container->get(LifecyleHooks::class);
		$hooks->load();
		/**
		 * @var ServerRequestCreatorInterface $requestFactory
		 */
		$requestFactory = $this->container->get(ServerRequestCreatorInterface::class);
		$response = $this->container->get(ResponseInterface::class);

		$request = $requestFactory->fromGlobals();

		$requestType = $hooks->determineType($request);

		try {
			$request = $hooks->preprocess($request, $requestType);
			if (method_exists($this->container, 'set')) {
				$this->container->set(ServerRequestInterface::class, $request);
			}
			$response = $hooks->process($request, $requestType, $response);
			$response = $hooks->postProcess($response, $requestType);
		} catch (Throwable $e) {
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
