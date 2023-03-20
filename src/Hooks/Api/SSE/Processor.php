<?php

namespace Bottledcode\SwytchFramework\Hooks\Api\SSE;

use Bottledcode\SwytchFramework\Hooks\Api\Invoker;
use Bottledcode\SwytchFramework\Hooks\Handler;
use Bottledcode\SwytchFramework\Router\SseMessage;
use Bottledcode\SwytchFramework\Template\Interfaces\StateProviderInterface;
use DI\FactoryInterface;
use Nyholm\Psr7\Factory\Psr17Factory;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Symfony\Component\Serializer\Serializer;

#[Handler(priority: 10)]
class Processor extends Invoker
{
	public function __construct(
		StateProviderInterface $stateProvider,
		Serializer $serializer,
		protected FactoryInterface $factory,
		Psr17Factory $psr17Factory
	) {
		parent::__construct($stateProvider, $serializer, $factory, $psr17Factory);
	}

	public function process(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
	{
		if ($request->getAttribute('sse') ?? false) {
			parent::process($request, $response);
		}
		return $response;
	}

	protected function invoke(
		string $class,
		string $method,
		array $arguments
	): mixed {
		register_shutdown_function(function () use ($class, $method, $arguments) {
			// output has already been emitted... so fall back to regular output systems.
			// send a blank line with enough data so old browser don't choke
			echo ":" . str_repeat(" ", 8096) . "\n\n";

			$component = $this->factory->make($class);
			callback:
			$generator = $component->$method(...$arguments);
			if ($generator instanceof \Generator) {
				foreach ($generator as $data) {
					if ($data instanceof SseMessage) {
						if(connection_aborted()) return;
						$this->emitMessage($data);
					}
				}
			}
			if ($generator instanceof SseMessage) {
				if(connection_aborted()) return;
				$this->emitMessage($generator);
				goto callback;
			}
		});

		return null;
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
		echo ":" . str_repeat(" ", 8096) . "\n\n";
	}
}
