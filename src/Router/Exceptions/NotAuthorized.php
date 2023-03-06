<?php

namespace Bottledcode\SwytchFramework\Router\Exceptions;

class NotAuthorized extends \RuntimeException
{
	public function __construct(\Throwable $previous = null)
	{
		parent::__construct("Not authorized", 401, $previous);
	}
}
