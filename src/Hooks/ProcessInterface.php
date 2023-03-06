<?php

namespace Bottledcode\SwytchFramework\Hooks;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

interface ProcessInterface
{
	public function process(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface;
}
