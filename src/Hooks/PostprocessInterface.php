<?php

namespace Bottledcode\SwytchFramework\Hooks;

use Psr\Http\Message\ResponseInterface;

interface PostprocessInterface
{
	public function postprocess(ResponseInterface $response): ResponseInterface;
}
