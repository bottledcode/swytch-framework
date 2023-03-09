<?php

namespace Bottledcode\SwytchFramework\Router\Exceptions;

use RuntimeException;
use Throwable;

class InvalidRequest extends RuntimeException
{
	public function __construct(string $message = 'Invalid request', int $code = 400, Throwable|null $previous = null)
	{
		parent::__construct($message, $code, $previous);
	}
}
