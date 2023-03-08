<?php

namespace Bottledcode\SwytchFramework\Hooks\Common;

use Bottledcode\SwytchFramework\Hooks\ExceptionHandlerInterface;
use Bottledcode\SwytchFramework\Hooks\Handler;
use Bottledcode\SwytchFramework\Hooks\RequestType;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Log\LoggerInterface;

#[Handler(1000)]
readonly class UnhandledError implements ExceptionHandlerInterface
{
	public function __construct(private LoggerInterface $logger)
	{
	}

	public function canHandle(\Throwable $exception, RequestType $type): bool
	{
		return true;
	}

	public function handleException(
		\Throwable $exception,
		ServerRequestInterface $request,
		ResponseInterface $response
	): ResponseInterface {
		if ($response->getStatusCode() === 200) {
			$this->logger->error('Unhandled exception', [
				'exception' => $exception,
				'request' => $request,
			]);
			return $response->withStatus(500);
		}
		return $response;
	}
}
