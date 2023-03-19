<?php

namespace Bottledcode\SwytchFramework\Hooks\Api\SSE;

use Bottledcode\SwytchFramework\Hooks\Api\ApiHandler;
use Bottledcode\SwytchFramework\Hooks\Api\Invoker;
use Bottledcode\SwytchFramework\Hooks\Api\Router;
use Bottledcode\SwytchFramework\Hooks\Common\Headers;
use Bottledcode\SwytchFramework\Hooks\Handler;
use Bottledcode\SwytchFramework\Hooks\PreprocessInterface;
use Bottledcode\SwytchFramework\Hooks\RequestType;
use Bottledcode\SwytchFramework\LifecyleHooks;
use Bottledcode\SwytchFramework\Router\Attributes\Route;
use Bottledcode\SwytchFramework\Router\Attributes\SseRoute as SseAttribute;
use olvlvl\ComposerAttributeCollector\Attributes;
use olvlvl\ComposerAttributeCollector\TargetMethod;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Log\LoggerInterface;

#[Handler(priority: 15)]
class Preprocessor extends ApiHandler implements PreprocessInterface
{

	public function __construct(
		private readonly Headers $headers,
		private readonly LoggerInterface $logger,
		private readonly Invoker $invoker,
		private readonly LifecyleHooks $lifecyleHooks
	) {
	}

	public function preprocess(ServerRequestInterface $request, RequestType $type): ServerRequestInterface
	{
		/**
		 * @var TargetMethod<Route>|null $route
		 */
		$route = $request->getAttribute(Router::ATTRIBUTE_HANDLER);

		foreach (Attributes::findTargetMethods(SseAttribute::class) as $targetMethod) {
			if ([$targetMethod->class, $targetMethod->name] === [$route->class, $route->name]) {
				ignore_user_abort(true);
				set_time_limit(0);

				// X-Accel-Buffering is only needed for Nginx, but it won't hurt on anything else.
				$this->headers->setHeader('X-Accel-Buffering', 'no');
				$this->headers->setHeader('Content-Type', 'text/event-stream');
				$this->headers->setHeader('Cache-Control', 'no-cache');
				$this->headers->setHeader('Connection', 'keep-alive');

				$this->logger->debug('SSE Preprocessor: SSE route found, setting headers.');
				$request = $request->withAttribute('sse', true);
				$this->lifecyleHooks->removeProcessor($this->invoker, 10);
			}
		}

		return $request;
	}
}
