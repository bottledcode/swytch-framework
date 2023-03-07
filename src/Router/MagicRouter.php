<?php

namespace Bottledcode\SwytchFramework\Router;

use Bottledcode\SwytchFramework\LifecyleHooks;
use Bottledcode\SwytchFramework\Router\Attributes\From;
use Bottledcode\SwytchFramework\Template\Attributes\Component;
use Bottledcode\SwytchFramework\Template\Compiler;
use Nyholm\Psr7Server\ServerRequestCreatorInterface;
use olvlvl\ComposerAttributeCollector\Attributes;
use olvlvl\ComposerAttributeCollector\TargetClass;
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

		$request = $hooks->preprocess($request, $requestType);
		$response = $hooks->process($request, $requestType, $response);
		$response = $hooks->postProcess($response, $requestType);
		return $response;
	}
}
