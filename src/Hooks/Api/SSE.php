<?php

namespace Bottledcode\SwytchFramework\Hooks\Api;

use Bottledcode\SwytchFramework\Hooks\Common\Headers;
use Bottledcode\SwytchFramework\Hooks\PostprocessInterface;
use Bottledcode\SwytchFramework\Hooks\PreprocessInterface;
use Bottledcode\SwytchFramework\Hooks\RequestType;
use Bottledcode\SwytchFramework\Router\Attributes\Route;
use Bottledcode\SwytchFramework\Router\Attributes\SseRoute as SseAttribute;
use Bottledcode\SwytchFramework\Router\SseMessage;
use Nyholm\Psr7\Factory\Psr17Factory;
use olvlvl\ComposerAttributeCollector\Attributes;
use olvlvl\ComposerAttributeCollector\TargetMethod;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Log\LoggerInterface;

class SSE extends ApiHandler implements PreprocessInterface, PostprocessInterface
{
	private \Closure $messageGenerator;
	private int $defaultRetryMs = 2000;
	private bool|null $isFirstMessage = null;

	public function __construct(
		private readonly Headers $headers,
		private readonly Psr17Factory $psr17Factory,
		private readonly LoggerInterface $logger
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

				$this->isFirstMessage = true;

				$this->messageGenerator = $targetMethod->attribute->messageGenerator;
				$this->defaultRetryMs = $targetMethod->attribute->retryMs;

				// X-Accel-Buffering is only needed for Nginx, but it won't hurt on anything else.
				$this->headers->setHeader('X-Accel-Buffering', 'no');
				$this->headers->setHeader('Content-Type', 'text/event-stream');
				$this->headers->setHeader('Cache-Control', 'no-cache');
				$this->headers->setHeader('Connection', 'keep-alive');
			}
		}

		return $request;
	}

	public function postprocess(ResponseInterface $response): ResponseInterface
	{
		if ($this->isFirstMessage === null) {
			return $response;
		}
		$response->getBody()->rewind();
		if (($response->getBody()->getSize() ?? 0) === 0) {
			register_shutdown_function($this->generateMessages(...));

			return $response->withBody(
				$this->psr17Factory->createStream(
					":" . str_repeat(" ", 2048) . "\nretry: " . $this->defaultRetryMs . "\n"
				)
			); // 2 kB padding for IE
		}

		return $response;
	}

	private function generateMessages(): void
	{
		$generator = $this->messageGenerator;
		$reflection = new \ReflectionFunction($generator);
		$returnType = $reflection->getReturnType();
		if ($returnType instanceof \ReflectionNamedType && $returnType->getName() === SseMessage::class) {
			while (true) {
				if (connection_aborted()) {
					break;
				}
				/** @var SseMessage $message */
				$message = $generator();
				$this->emitMessage($message);
			}
		}
		if ($returnType instanceof \ReflectionNamedType && $returnType->getName() === \Generator::class) {
			foreach ($generator() as $message) {
				if (connection_aborted()) {
					break;
				}
				$this->emitMessage($message);
			}
		}
		$this->logger->warning("SSE connection closed due to incorrect handler return type: " . $returnType?->getName() ?? '');
	}

	private function emitMessage(SseMessage $message): void
	{
		echo "event: " . $message->event . "\n";
		if ($message->id) {
			echo "id: " . $message->id . "\n";
		}
		if ($message->retryMs) {
			echo "retry: " . $message->retryMs . "\n";
		}
		echo "data: " . $message->data . "\n\n";
	}
}
