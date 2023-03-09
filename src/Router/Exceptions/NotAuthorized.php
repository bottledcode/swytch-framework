<?php

namespace Bottledcode\SwytchFramework\Router\Exceptions;

use RuntimeException;
use Throwable;

class NotAuthorized extends RuntimeException
{
	public function __construct(Throwable|null $previous = null)
	{
		parent::__construct("Not authorized", 401, $previous);
	}
}
