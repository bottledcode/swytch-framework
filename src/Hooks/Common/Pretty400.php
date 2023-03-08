<?php

namespace Bottledcode\SwytchFramework\Hooks\Common;

use Bottledcode\SwytchFramework\Hooks\ExceptionHandlerInterface;
use Bottledcode\SwytchFramework\Hooks\Handler;
use Bottledcode\SwytchFramework\Hooks\RequestType;
use Bottledcode\SwytchFramework\Router\Exceptions\InvalidRequest;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Log\LoggerInterface;

#[Handler(1)]
readonly class Pretty400 implements ExceptionHandlerInterface
{
	public function __construct(private LoggerInterface $logger)
	{
	}

	public function canHandle(\Throwable $exception, RequestType $type): bool
	{
		return $exception instanceof InvalidRequest;
	}

	public function handleException(
		\Throwable $exception,
		ServerRequestInterface $request,
		ResponseInterface $response
	): ResponseInterface {
		$this->logger->error('Invalid request', [
			'exception' => $exception,
			'request' => $request,
		]);
		return $response->withStatus(400);
	}
}
