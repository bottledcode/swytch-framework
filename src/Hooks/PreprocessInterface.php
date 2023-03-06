<?php

namespace Bottledcode\SwytchFramework\Hooks;

use Psr\Http\Message\ServerRequestInterface;

interface PreprocessInterface
{
	public function preprocess(ServerRequestInterface $request, RequestType $type): ServerRequestInterface;
}
