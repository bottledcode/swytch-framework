<?php

namespace Bottledcode\SwytchFramework\Router\Exceptions;

class InvalidRequest extends \RuntimeException
{
	public function __construct(string $message = 'Invalid request', int $code = 400, \Throwable $previous = null)
	{
		parent::__construct($message, $code, $previous);
	}
}
