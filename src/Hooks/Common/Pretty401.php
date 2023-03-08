<?php

namespace Bottledcode\SwytchFramework\Hooks\Common;

use Bottledcode\SwytchFramework\Hooks\ExceptionHandlerInterface;
use Bottledcode\SwytchFramework\Hooks\Handler;
use Bottledcode\SwytchFramework\Hooks\RequestType;
use Bottledcode\SwytchFramework\Router\Exceptions\NotAuthorized;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Log\LoggerInterface;

#[Handler(1)]
readonly class Pretty401 implements ExceptionHandlerInterface
{
	public function __construct(private LoggerInterface $logger)
	{
	}

	public function canHandle(\Throwable $exception, RequestType $type): bool
	{
		return $exception instanceof NotAuthorized;
	}

	public function handleException(
		\Throwable $exception,
		ServerRequestInterface $request,
		ResponseInterface $response
	): ResponseInterface {
		$this->logger->info('Not authorized', [
			'exception' => $exception,
			'request' => $request,
		]);
		return $response->withStatus(401);
	}
}
