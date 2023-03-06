<?php

namespace Bottledcode\SwytchFramework\Hooks\Common;

use Bottledcode\SwytchFramework\Hooks\HandleRequestInterface;
use Bottledcode\SwytchFramework\Hooks\PostprocessInterface;
use Bottledcode\SwytchFramework\Hooks\ProcessInterface;
use Bottledcode\SwytchFramework\Hooks\RequestType;
use Bottledcode\SwytchFramework\Router\Method;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

class Headers implements HandleRequestInterface, PostprocessInterface
{

	/**
	 * @var array<string, array<string>> $headers
	 */
	private array $headers = [];

	public function handles(RequestType $requestType): bool
	{
		return true;
	}

	public function setHeader(string $name, string $value, bool $overwrite = false): void
	{
		if ($overwrite) {
			$this->headers[$name] = [$value];
			return;
		}
		$this->headers[$name][] = $value;
	}

	public function postprocess(ResponseInterface $response): ResponseInterface
	{
		foreach($this->headers as $header => $values) {
			$response = $response->withHeader($header, $values);
		}
		return $response;
	}
}
