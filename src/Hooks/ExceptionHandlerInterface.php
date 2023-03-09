<?php

namespace Bottledcode\SwytchFramework\Hooks;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Throwable;

interface ExceptionHandlerInterface
{
	public function canHandle(Throwable $exception, RequestType $type): bool;

	public function handleException(
		Throwable $exception,
		ServerRequestInterface $request,
		ResponseInterface $response
	): ResponseInterface;
}
