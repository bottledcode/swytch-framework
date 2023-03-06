<?php

namespace Bottledcode\SwytchFramework\Hooks\Api;

use Bottledcode\SwytchFramework\Hooks\PreprocessInterface;
use Bottledcode\SwytchFramework\Hooks\RequestType;
use Bottledcode\SwytchFramework\Router\Attributes\Authorized;
use Bottledcode\SwytchFramework\Router\Attributes\Route;
use Bottledcode\SwytchFramework\Router\Exceptions\NotAuthorized;
use Bottledcode\SwytchFramework\Template\Interfaces\AuthenticationServiceInterface;
use olvlvl\ComposerAttributeCollector\Attributes;
use olvlvl\ComposerAttributeCollector\TargetMethod;
use Psr\Container\ContainerInterface;
use Psr\Http\Message\ServerRequestInterface;

class Authorization extends ApiHandler implements PreprocessInterface
{

	public function __construct(private readonly ContainerInterface $container)
	{
	}

	public function preprocess(ServerRequestInterface $request, RequestType $type): ServerRequestInterface
	{
		/**
		 * @var TargetMethod<Route>|null $route
		 */
		$route = $request->getAttribute(Router::ATTRIBUTE_HANDLER);

		foreach (Attributes::findTargetMethods(Authorized::class) as $targetMethod) {
			if ([$targetMethod->class, $targetMethod->name] === [$route->class, $route->name]) {
				/**
				 * @var AuthenticationServiceInterface $authorizer
				 */
				$authorizer = $this->container->get(AuthenticationServiceInterface::class);
				$authorized = $authorizer->isAuthorizedVia(...$targetMethod->attribute->roles);
				if (!$authorized) {
					throw new NotAuthorized();
				}
				break;
			}
		}

		return $request;
	}
}
