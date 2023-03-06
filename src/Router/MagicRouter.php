<?php

namespace Bottledcode\SwytchFramework\Router;

use Bottledcode\SwytchFramework\LifecyleHooks;
use Bottledcode\SwytchFramework\Router\Attributes\From;
use Bottledcode\SwytchFramework\Template\Attributes\Component;
use Bottledcode\SwytchFramework\Template\Compiler;
use Bottledcode\SwytchFramework\Template\Interfaces\StateProviderInterface;
use Nyholm\Psr7Server\ServerRequestCreatorInterface;
use olvlvl\ComposerAttributeCollector\Attributes;
use olvlvl\ComposerAttributeCollector\TargetClass;
use Psr\Container\ContainerInterface;
use Psr\Http\Message\ResponseInterface;

class MagicRouter
{
	public string $lastEtag = '';
	private array $middleware = [];
	private readonly StateProviderInterface $stateProvider;

	public function __construct(private ContainerInterface $container, private string $appRoot)
	{
		$this->stateProvider = $this->container->get(StateProviderInterface::class);
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
		/**
		 * @var Compiler $compiler
		 */
		$compiler = $this->container->get(Compiler::class);
		array_map(
			static fn(TargetClass $class) => $compiler->registerComponent($class),
			Attributes::findTargetClasses(Component::class)
		);

		$requestType = $hooks->determineType($request);

		$request = $hooks->preprocess($request, $requestType);
		$response = $hooks->process($request, $requestType, $response);
		$response = $hooks->postProcess($response, $requestType);
		return $response;
	}
}
